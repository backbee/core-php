<?php

namespace BackBeeCloud\Page;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\NestedNode\Page;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use Doctrine\ORM\EntityManagerInterface;

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
            $data['uid'] = $entry['_id'];
            $page = $entry['_source'];
            $data['type'] = $page['type'];
            $data['title'] = $page['title'];
            $data['contents'] = $page['contents'];
            $data['url'] = $page['url'];
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
        if (null === $abstractUid) {
            $this->data['abstract'] = null;
        } else {
            $abstract = $this->getContentWithDraft(ArticleAbstract::class, $abstractUid);
            $abstract = $abstract ?: $this->getContentWithDraft(Paragraph::class, $abstractUid);
            if (null !== $abstract) {
                $this->data['abstract'] = trim(
                    preg_replace('#\s\s+#', ' ', preg_replace('#<[^>]+>#', ' ', $abstract->value))
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
        if (null === $mediaUid) {
            $this->data['image'] = null;
        } else {
            $image = null;
            $media = $this->getContentWithDraft(AbstractClassContent::class, $mediaUid);
            if ($media instanceof Image && null !== ($image = $media->image)) {
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
    private function getContentWithDraft(string $classname, string $uid): ?object
    {
        return $this->entityManager->find($classname, $uid);
    }
}
