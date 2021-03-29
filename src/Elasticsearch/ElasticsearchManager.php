<?php

namespace BackBeeCloud\Elasticsearch;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Article\ArticleTitle;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Basic\Title;
use BackBee\ClassContent\Media\Video;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\NestedNode\Page;
use BackBee\Security\Token\BBUserToken;
use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Entity\PageTag;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeInterface;
use BackBeeCloud\PageType\TypeManager;
use BackBeePlanet\Job\ElasticsearchJob;
use BackBeePlanet\Job\JobInterface;
use Exception;
use stdClass;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ElasticsearchManager extends ElasticsearchClient implements JobHandlerInterface
{
    /**
     * @var ContentManager
     */
    protected $contentMgr;

    /**
     * @var TypeManager
     */
    protected $pagetypeMgr;

    /**
     * @var BBUserToken
     */
    protected $bbtoken;

    /**
     * @var PageCategoryManager
     */
    protected $pageCategoryManager;

    /**
     * @var ElasticSearchQuery
     */
    protected $elasticSearchQuery;

    /**
     * ElasticsearchManager constructor.
     *
     * @param BBApplication $app
     */
    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->contentMgr = $app->getContainer()->get('cloud.content_manager');
        $this->pagetypeMgr = $app->getContainer()->get('cloud.page_type.manager');
        $this->pageCategoryManager = $app->getContainer()->get('cloud.page_category.manager');
        $this->elasticSearchQuery = $app->getContainer()->get('elasticsearch.query');
        $this->bbtoken = $app->getBBUserToken();
    }

    /**
     * Deletes the index if it exist and create a new one.
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
        $type = $this->pagetypeMgr->findByPage($page);

        $data = [
            'title' => $this->extractTitleFromPage($page),
            'first_heading' => $this->getFirstHeadingFromPage($page),
            'abstract_uid' => $this->extractAbstractUidFromPage($page),
            'tags' => [],
            'url' => $page->getUrl(),
            'image_uid' => $this->extractImageUidFromPage($page),
            'contents' => $this->extractTextsFromPage($page),
            'type' => $type instanceof TypeInterface ? $type->uniqueName() : '',
            'is_online' => $page->isOnline(),
            'is_pullable' => $type instanceof TypeInterface ? $type->isPullable() : false,
            'created_at' => $page->getCreated()->format('Y-m-d H:i:s'),
            'modified_at' => $page->getModified()->format('Y-m-d H:i:s'),
            'published_at' => $page->getPublishing() ? $page->getPublishing()->format('Y-m-d H:i:s') : null,
            'has_draft_contents' => $this->bbtoken
                ? $this->contentMgr->isDraftedPage($page, $this->bbtoken)
                : false
            ,
            'category' => $this->pageCategoryManager->getCategoryByPage($page),
            'images' => $this->contentMgr->getAllImageForAnPage($page)
        ];

        $pageTag = $this->entityMgr->getRepository(PageTag::class)->findOneBy(['page' => $page]);

        foreach ($pageTag !== null ? $pageTag->getTags() : [] as $tag) {
            $data['tags'][] = strtolower($tag->getKeyWord());
        }

        return $data;
    }

    /**
     * Removes the provided page from Elasticsearch.
     *
     * @param Page $page
     *
     * @return ElasticsearchManager
     */
    public function deletePage(Page $page)
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
     * @param int  $limit
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

        if (false !== $sort) {
            $criteria['sort'] = $sort;
        }

        $result = $this->getClient()->search($criteria);

        $pages = $result['hits']['hits'];

        if ($formatResult) {
            $uids = array_column($pages, '_id');
            if (false !== $uids) {
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
     * returns all tags with pagination.
     *
     * Note that tags are ordered by its name (ascending).
     *
     * @param string|null $prefix
     * @param int         $start
     * @param int         $limit
     *
     * @return ElasticsearchCollection
     */
    public function searchTag(?string $prefix, int $start, int $limit): ElasticsearchCollection
    {
        $filter = [
            'match_all' => new stdClass,
        ];
        if (null !== $prefix) {
            $filter = [
                'prefix' => [
                    'name' => strtolower($prefix),
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
                            'constant_score' => [
                                'filter' => $filter,
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
        if (false !== $uids) {
            $tags = $this->sortEntitiesByUids(
                $uids,
                $this->entityMgr->getRepository(Tag::class)->findBy(['_uid' => $uids])
            );
        }

        return new ElasticsearchCollection($tags, $result['hits']['total']);
    }

    /**
     * Get all image for an page by uid.
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
                                    [ 'match' => ['_id' => $uid] ],
                                ]
                            ],
                        ],
                        '_source' => ['images']
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
     * Extract title form page.
     *
     * @param Page $page
     *
     * @return string
     */
    protected function extractTitleFromPage(Page $page): string
    {
        return $page->getTitle();
    }

    /**
     * Extract abstract uid from page.
     *
     * @param Page $page
     *
     * @return null|string
     */
    protected function extractAbstractUidFromPage(Page $page): ?string
    {
        $contentUids = $this->contentMgr->getUidsFromPage($page, $this->bbtoken);
        $abstract = $this->getRealFirstContentByUid(
            $this->entityMgr->getRepository(ArticleAbstract::class)->findBy(
                [
                    '_uid' => $contentUids,
                ]
            ),
            $contentUids
        );

        $abstract = $abstract ?: null;
        if ($abstract === null) {
            $abstract = $this->getRealFirstContentByUid(
                $this->entityMgr->getRepository(Paragraph::class)->findBy(
                    [
                        '_uid' => $contentUids,
                    ]
                ),
                $contentUids
            );
        }

        return $abstract ? $abstract->getUid() : null;
    }

    /**
     * Extract texts form page.
     *
     * @param Page $page
     *
     * @return string
     */
    protected function extractTextsFromPage(Page $page): string
    {
        $contentUids = $this->contentMgr->getUidsFromPage($page, $this->bbtoken);

        $titles = array_merge(
            $this->entityMgr->getRepository(Title::class)->findBy(
                [
                    '_uid' => $contentUids,
                ]
            ),
            $this->entityMgr->getRepository(ArticleTitle::class)->findBy(
                [
                    '_uid' => $contentUids,
                ]
            )
        );

        $result = [];
        foreach ($titles as $title) {
            $title->setDraft(null);
            $result[] = $title->value;
        }

        $abstracts = $this->entityMgr->getRepository(ArticleAbstract::class)->findBy(
            [
                '_uid' => $contentUids,
            ]
        );

        foreach ($abstracts as $abstract) {
            $abstract->setDraft(null);
            $result[] = $abstract->value;
        }

        $paragraphs = $this->entityMgr->getRepository(Paragraph::class)->findBy(
            [
                '_uid' => $contentUids,
            ]
        );

        foreach ($paragraphs as $paragraph) {
            $paragraph->setDraft(null);
            $result[] = $paragraph->value;
        }

        return $this->cleanText(implode(' ', $result));
    }

    /**
     * Get first heading form page.
     *
     * @param Page $page
     *
     * @return string
     */
    protected function getFirstHeadingFromPage(Page $page): string
    {
        $contentIds = $this->contentMgr->getUidsFromPage($page, $this->bbtoken);
        $title = $this->getRealFirstContentByUid(
            [
                $this->entityMgr->getRepository(ArticleTitle::class)->findOneBy(['_uid' => $contentIds]),
                $this->entityMgr->getRepository(Title::class)->findOneBy(['_uid' => $contentIds]),
            ],
            $contentIds
        );

        return $title ? trim(strip_tags($title->value)) : '';
    }

    /**
     * Extract image uid from page.
     *
     * @param Page $page
     *
     * @return string|null
     */
    protected function extractImageUidFromPage(Page $page): ?string
    {
        $contentUids = $this->contentMgr->getUidsFromPage($page, $this->bbtoken);
        $media = $this->getRealFirstContentByUid(
            array_merge(
                $this->entityMgr->getRepository(Image::class)->findBy(
                    [
                        '_uid' => $contentUids,
                    ]
                ),
                $this->entityMgr->getRepository(Video::class)->findBy(
                    [
                        '_uid' => $contentUids,
                    ]
                )
            ),
            $contentUids
        );

        if ($media instanceof Video) {
            if ($media->thumbnail->image->path === false || AbstractClassContent::STATE_NORMAL !== $media->getState()) {
                $contentUids = array_filter(
                    $contentUids,
                    static function ($uid) use ($media) {
                        return $uid !== $media->getUid();
                    }
                );
                $media = $this->getRealFirstContentByUid(
                    $this->entityMgr->getRepository(Image::class)->findBy(
                        [
                            '_uid' => $contentUids,
                        ]
                    ),
                    $contentUids
                );
            }
        }

        return $media ? $media->getUid() : null;
    }

    /**
     * Clean text.
     *
     * @param $text
     *
     * @return string
     */
    protected function cleanText($text): string
    {
        return trim(preg_replace('#\s\s+#', ' ', preg_replace('#<[^>]+>#', ' ', $text)));
    }

    /**
     * Get real first content by uid.
     *
     * @param array $contents
     * @param array $orders
     *
     * @return AbstractClassContent|null
     */
    protected function getRealFirstContentByUid(array $contents, array $orders): ?AbstractClassContent
    {
        $firstContent = array_pop($contents);

        if ($firstContent instanceof AbstractClassContent) {
            $curPos = array_search($firstContent->getUid(), $orders, true);
            foreach ($contents as $content) {
                if (null !== $content && $curPos > $pos = array_search($content->getUid(), $orders, true)) {
                    $curPos = $pos;
                    $firstContent = $content;
                }
            }
        }

        return $firstContent;
    }
}
