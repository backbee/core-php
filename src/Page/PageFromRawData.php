<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBeeCloud\Page;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\NestedNode\Page;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use Doctrine\ORM\EntityManagerInterface;
use function strlen;

/**
 * Class PageFromRawData
 *
 * @package BackBee\Page
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageFromRawData
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ElasticsearchManager
     */
    private $elasticsearchManager;

    /**
     * @var array
     */
    private $data;

    /**
     * PageFromRawData constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param ElasticsearchManager   $elasticsearchManager
     */
    public function __construct(EntityManagerInterface $entityManager, ElasticsearchManager $elasticsearchManager)
    {
        $this->entityManager = $entityManager;
        $this->elasticsearchManager = $elasticsearchManager;
        $this->data = [];
    }

    /**
     * Get data.
     *
     * @param Page $page
     *
     * @return array
     */
    public function getData(Page $page): array
    {
        $entries = $this->getESResult($page);

        foreach ($entries as $entry) {
            $this->data['uid'] = $entry['_id'];
            $page = $entry['_source'];
            $this->data['type'] = $page['type'];
            $this->data['title'] = $page['title'];
            $this->data['contents'] = $page['contents'];
            $this->data['url'] = $page['url'];
            $this->setAbstractData($page['abstract_uid'] ?? null);
            $this->setImageData($page['image'] ?? null);
            $this->data['tags'] = $page['tags'];
            $this->data['created_at'] = $page['created_at'];
            $this->data['modified_at'] = $page['modified_at'];
            $this->data['published_at'] = $page['published_at'];
        }

        return $this->data;
    }

    /**
     * Set abstract data.
     *
     * @param string|null $abstractUid
     */
    private function setAbstractData(?string $abstractUid): void
    {
        if ($abstractUid === null) {
            $this->data['abstract'] = null;
        } else {
            $abstract = $this->getContentWithDraft(ArticleAbstract::class, $abstractUid);
            $abstract = $abstract ?: $this->getContentWithDraft(Paragraph::class, $abstractUid);
            if ($abstract !== null) {
                $this->data['abstract'] = mb_substr(
                    trim(
                        html_entity_decode(
                            strip_tags(
                                preg_replace(
                                    ['#<[^>]+>#', '#\s\s+#', '#&nbsp;#', '/\\\\n/', '#"#'],
                                    [' ', ' ', '', ''],
                                    $abstract->value
                                )
                            )
                        )
                    ),
                    0,
                    300
                );
            }
        }
    }

    /**
     * Set image data.
     *
     * @param $mediaUid
     */
    private function setImageData($mediaUid): void
    {
        if ($mediaUid === null) {
            $this->data['image'] = null;
        } else {
            $media = $this->getContentWithDraft(AbstractClassContent::class, $mediaUid);
            if ($media instanceof Image && ($image = $media->image) !== null) {
                $this->data['image'] = [
                    'uid' => $image->getUid(),
                    'url' => $image->path,
                    'title' => $image->getParamValue('title'),
                    'legend' => $image->getParamValue('description'),
                    'stat' => $image->getParamValue('stat'),
                ];
            }
        }
    }

    /**
     * Get elastic search result.
     *
     * @param Page $page
     *
     * @return array|ElasticsearchCollection
     */
    private function getESResult(Page $page)
    {
        $shouldMatch[] = ['match' => ['_id' => $page->getUid()]];
        $entries = $this->elasticsearchManager->customSearchPage(
            [
                'query' => [
                    'bool' => [
                        'should' => $shouldMatch,
                        'minimum_should_match' => 1,
                    ],
                ],
            ],
            0,
            1,
            [],
            false
        );

        return $entries->count() === 0 ? [] : $entries;
    }

    /**
     * Get content with draft.
     *
     * @param string $classname
     * @param string $uid
     *
     * @return object|null
     */
    private function getContentWithDraft(string $classname, string $uid)
    {
        return $this->entityManager->find($classname, $uid);
    }
}
