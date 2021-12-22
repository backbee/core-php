<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

namespace BackBeeCloud\Elasticsearch;

use BackBee\BBApplication;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\NestedNode\Page;
use BackBee\Page\PageContentManager;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\SearchEngine\SearchEngineManager;
use BackBeePlanet\Job\ElasticsearchJob;
use BackBeePlanet\Job\JobInterface;
use Cocur\Slugify\Slugify;
use Exception;
use stdClass;

/**
 * Class ElasticsearchManager
 *
 * @package BackBeeCloud\Elasticsearch
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ElasticsearchManager extends ElasticsearchClient implements JobHandlerInterface
{
    public const INDEX_BASE_NAME = 'backbee_standalone_';

    /**
     * @var ElasticSearchQuery
     */
    protected $elasticSearchQuery;

    /**
     * @var PageContentManager
     */
    protected $pageContentManager;

    /**
     * @var MultiLangManager
     */
    protected $multiLangManager;

    /**
     * @var \BackBee\Config\Config
     */
    protected $config;

    /**
     * @var \BackBeeCloud\SearchEngine\SearchEngineManager
     */
    protected $searchEngineManager;

    /**
     * ElasticsearchManager constructor.
     *
     * @param BBApplication                                  $app
     * @param PageContentManager                             $pageContentManager
     * @param MultiLangManager                               $multiLangManager
     * @param ElasticsearchQuery                             $elasticsearchQuery
     * @param \BackBeeCloud\SearchEngine\SearchEngineManager $searchEngineManager
     */
    public function __construct(
        BBApplication $app,
        PageContentManager $pageContentManager,
        MultiLangManager $multiLangManager,
        ElasticsearchQuery $elasticsearchQuery,
        SearchEngineManager $searchEngineManager
    ) {
        parent::__construct($app, $app->getContainer()->get('config'));

        $this->elasticSearchQuery = $elasticsearchQuery;
        $this->pageContentManager = $pageContentManager;
        $this->multiLangManager = $multiLangManager;
        $this->config = $app->getContainer()->get('config')->getSection('elasticsearch');
        $this->searchEngineManager = $searchEngineManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexName(): string
    {
        return (new Slugify())->slugify($this->config['index_name'] ?? (self::INDEX_BASE_NAME . $this->getSiteName()));
    }

    /**
     * Deletes the index if it exists and create a new one.
     */
    public function resetIndex(): ElasticsearchManager
    {
        $params = ['index' => $this->getIndexName()];

        if ($this->getClient()->indices()->exists($params)) {
            $this->getClient()->indices()->delete($params);
        }

        return $this->createIndex();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPageCustomDataToIndex(Page $page): array
    {
        $type = $this->pageContentManager->getTypeByPage($page);
        $searchEngine = $this->searchEngineManager->googleSearchEngineIsActivated();

        return [
            'title' => $this->pageContentManager->extractTitleFromPage($page),
            'first_heading' => $this->pageContentManager->getFirstHeadingFromPage($page),
            'abstract_uid' => $this->pageContentManager->extractAbstractUidFromPage($page),
            'tags' => $this->pageContentManager->getTagsByPage($page),
            'url' => $page->getUrl(),
            'image_uid' => $this->pageContentManager->extractImageUidFromPage($page),
            'contents' => $this->pageContentManager->extractTextsFromPage($page),
            'type' => $type ? $type->uniqueName() : '',
            'is_online' => $page->isOnline(),
            'is_pullable' => $type && $type->isPullable(),
            'level' => $page->getLevel(),
            'state' => $page->getState(),
            'created_at' => $page->getCreated()->format('Y-m-d H:i:s'),
            'modified_at' => $page->getModified()->format('Y-m-d H:i:s'),
            'published_at' => $page->getPublishing() ? $page->getPublishing()->format('Y-m-d H:i:s') : null,
            'has_draft_contents' => $this->pageContentManager->hasDraftContents($page),
            'category' => $this->pageContentManager->getCategoryByPage($page),
            'images' => $this->pageContentManager->getImagesByPage($page),
            'lang' => $this->multiLangManager->getLangByPage($page) ?? 'fr',
            'seo_index' => null === $page->getMetaData()->get('index') ?
                $searchEngine : $page->getMetaData()->get('index')->getAttribute('content'),
            'seo_follow' => null === $page->getMetaData()->get('follow') ?
                $searchEngine : $page->getMetaData()->get('follow')->getAttribute('content'),
        ];
    }

    /**
     * Override this method if you want to index some custom property for tag.
     *
     * @param Tag $tag
     *
     * @return array
     */
    protected function getTagCustomDataToIndex(Tag $tag): array
    {
        $parents = [];
        $parent = $tag->getParent();

        while ($parent) {
            $parents[] = $parent->getUid();
            $parent = $parent->getParent();
        }

        return [
            'parents' => array_reverse($parents),
        ];
    }

    /**
     * Removes the provided page from Elasticsearch.
     *
     * @param Page $page
     *
     * @return ElasticsearchManager
     */
    public function deletePage(Page $page): ElasticsearchManager
    {
        $this->getClient()->delete(['index' => $this->getIndexName(), 'id' => $page->getUid()]);

        return $this;
    }

    /**
     * Searches pages against the provided term and return an ElasticsearchCollection
     * of Page entity.
     *
     * @param string $term
     * @param int    $start
     * @param int    $limit
     * @param array  $sort
     *
     * @return ElasticsearchCollection
     */
    public function searchPage(string $term, int $start = 0, int $limit = 25, array $sort = []): ElasticsearchCollection
    {
        return $this->customSearchPage(
            $this->elasticSearchQuery->getDefaultBooleanQuery($term),
            $start,
            $limit,
            $sort
        );
    }

    /**
     * Searches pages against the provided body.
     *
     * @param array    $body
     * @param int|null $start
     * @param int      $limit
     * @param array    $sort
     * @param bool     $formatResult
     *
     * @return ElasticsearchCollection
     */
    public function customSearchPage(
        array $body,
        int $start = 0,
        int $limit = 25,
        array $sort = [],
        bool $formatResult = true
    ): ElasticsearchCollection {
        $criteria = [
            'index' => $this->getIndexName(),
            'from' => $start,
            'size' => $limit,
            'body' => $body,
        ];

        if (!empty($sort)) {
            $criteria['sort'] = $sort;
        }

        $result = $this->getClient()->search($criteria);

        $pages = $result['hits']['hits'];

        if ($formatResult) {
            $uids = array_column($pages, '_id');
            if (!empty($uids)) {
                $pages = $this->sortEntitiesByUids(
                    $uids,
                    $this->entityMgr->getRepository(Page::class)->findBy(['_uid' => $uids])
                );
            }
        }

        return new ElasticsearchCollection($pages, $result['hits']['total']['value'], $start, $limit);
    }

    /**
     * Delete the provided tag from Elasticsearch's index.
     *
     * @param Tag $tag
     *
     * @return self
     */
    public function deleteTag(Tag $tag): ElasticsearchManager
    {
        $this->getClient()->delete(['index' => $this->getIndexName(), 'id' => $tag->getUid()]);

        return $this;
    }

    /**
     * Searches tag with the provided prefix. If `prefix == false`, it will
     * return all tags with pagination.
     *
     * Note that tags are ordered by its name (ascending).
     *
     * @param string|null $prefix
     * @param null|string $context
     * @param int         $start
     * @param int         $limit
     *
     * @return ElasticsearchCollection
     */
    public function searchTag(?string $prefix, ?string $context, int $start, int $limit): ElasticsearchCollection
    {
        $must = [
            'match_all' => new stdClass,
        ];

        if ($prefix !== null) {
            $must = [
                [
                    'match_phrase' => [
                        'source' => Tag::SOURCE_TYPE,
                    ],
                ],
                [
                    'match_phrase' => [
                        'name' => [
                            'query' => $prefix,
                        ],
                    ],
                ],
            ];
        }

        if ($context === 'get_parent') {
            $must[] = [
                'script' => [
                    'script' => "doc['parents'].size() < 2",
                ],
            ];
        }

        try {
            $result = $this->getClient()->search(
                [
                    'index' => $this->getIndexName(),
                    'from' => $start,
                    'size' => $limit,
                    'body' => [
                        'query' => [
                            'bool' => [
                                'must' => $must,
                            ],
                        ],
                        'sort' => [
                            'name' => [
                                'order' => 'asc',
                            ],
                        ],
                    ],
                ]
            );
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->warning(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );

            return new ElasticsearchCollection([], 0);
        }

        $uids = [];

        foreach ($result['hits']['hits'] as $document) {
            $uids[] = $document['_id'];
        }

        $tags = [];

        if (!empty($uids)) {
            $tags = $this->sortEntitiesByUids(
                $uids,
                $this->entityMgr->getRepository(Tag::class)->findBy(['_uid' => $uids])
            );
        }

        return new ElasticsearchCollection($tags, $result['hits']['total']);
    }

    /**
     * Get all image for a page by uid.
     *
     * @param string $uid
     *
     * @return array
     */
    public function getAllImageForAnPageByUid(string $uid): array
    {
        $images = [];

        try {
            $result = $this->getClient()->search(
                [
                    'index' => $this->getIndexName(),
                    'body' => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    ['match' => ['_id' => $uid]],
                                ],
                            ],
                        ],
                        '_source' => ['images'],
                    ],
                ]
            );

            foreach ($result['hits']['hits'] as $document) {
                $images = $document['_source']['images'];
            }
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $images;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer): void
    {
        $writer->write('  <c2>> Create index...</c2>');
        $this->createIndex();

        $writer->write('  <c2>> Create types...</c2>');
        $this->createTypes();

        $writer->write('  <c2>> Reindexing all pages...</c2>');
        $this->indexAllPages(true);

        $writer->write('  <c2>> Reindexing all tags...</c2>');
        $this->indexAllTags();

        $writer->write('');
        $writer->write('Contents are now re-indexed into Elasticsearch.');
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job): bool
    {
        return $job instanceof ElasticsearchJob;
    }

    /**
     * Sorts the provided collection by the order defined in the uids array.
     *
     * Note that provided entities collection must have the method ::getUid().
     *
     * @param array $uids
     * @param array $entities
     *
     * @return array
     */
    protected function sortEntitiesByUids(array $uids, array $entities): array
    {
        $sorted = [];
        $positions = array_flip($uids);
        foreach ($entities as $entity) {
            $sorted[$positions[$entity->getUid()]] = $entity;
        }

        ksort($sorted);

        return array_values($sorted);
    }

    /**
     * Get page by uid.
     *
     * @param string $uid
     *
     * @return array|null
     */
    public function getPageByUid(string $uid): ?array
    {
        $page = $this->getClient()->get(
            [
                'id' => $uid,
                'index' => $this->getIndexName(),
            ]
        );

        return $page['found'] ? $page['_source'] : null;
    }
}
