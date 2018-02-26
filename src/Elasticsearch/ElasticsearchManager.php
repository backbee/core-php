<?php

namespace BackBeeCloud\Elasticsearch;

use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeePlanet\ElasticsearchManager as PlanetElasticsearchManager;
use BackBeePlanet\Job\ElasticsearchJob;
use BackBeePlanet\Job\JobInterface;
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

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ElasticsearchManager extends PlanetElasticsearchManager implements JobHandlerInterface
{
    /**
     * @var \BackBeeCloud\Entity\ContentManager
     */
    protected $contentMgr;

    /**
     * @var \BackBeeCloud\PageType\TypeManager
     */
    protected $pagetypeMgr;

    /**
     * @var \BackBee\Security\Token\BBUserToken
     */
    protected $bbtoken;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app->getEntityManager());

        $this->contentMgr = $app->getContainer()->get('cloud.content_manager');
        $this->pagetypeMgr = $app->getContainer()->get('cloud.page_type.manager');
        $this->bbtoken = $app->getBBUserToken();
    }

    /**
     * Deletes the index if it exist and create a new one.
     *
     * @param  boolean $replace
     * @return self
     */
    public function resetIndex()
    {
        $params = ['index' => $this->getIndexName()];
        if ($this->getClient()->indices()->exists($params)) {
            $this->getClient()->indices()->delete($params);
        }

        return $this->createIndex();
    }

    /**
     * Indexes the provide page into the 'page' type.
     *
     * @param  Page   $page
     * @return self
     */
    public function indexPage(Page $page)
    {
        $type = $this->pagetypeMgr->findByPage($page);

        $params = [
            'index' => $this->getIndexName(),
            'type'  => $this->getPageTypeName(),
            'id'    => $page->getUid(),
            'body'  => [
                'title'              => $this->extractTitleFromPage($page),
                'abstract_uid'       => $this->extractAbstractUidFromPage($page),
                'tags'               => [],
                'url'                => $page->getUrl(),
                'image_uid'          => $this->extractImageUidFromPage($page),
                'contents'           => $this->extractTextsFromPage($page),
                'type'               => $type->uniqueName(),
                'is_online'          => $page->isOnline(),
                'is_pullable'        => $type->isPullable(),
                'created_at'         => $page->getCreated()->format('Y-m-d H:i:s'),
                'modified_at'        => $page->getModified()->format('Y-m-d H:i:s'),
                'published_at'       => $page->getPublishing() ? $page->getPublishing()->format('Y-m-d H:i:s') : null,
                'has_draft_contents' => $this->bbtoken
                    ? $this->contentMgr->isDraftedPage($page, $this->bbtoken)
                    : false
                ,
            ],
        ];

        $pagetag = $this
            ->entyMgr
            ->getRepository('BackBeeCloud\Entity\PageTag')
            ->findOneBy(['page' => $page])
        ;
        $tags = null !== $pagetag ? $pagetag->getTags() : [];
        foreach ($tags as $tag) {
            $params['body']['tags'][] = strtolower($tag->getKeyWord());
        }

        $this->getClient()->index($params);

        return $this;
    }

    /**
     * Removes the provided page from Elasticsearch.
     *
     * @param  Page   $page
     * @return self
     */
    public function deletePage(Page $page)
    {
        $this->getClient()->delete([
            'index' => $this->getIndexName(),
            'type'  => $this->getPageTypeName(),
            'id'    => $page->getUid(),
        ]);

        return $this;
    }

    /**
     * Searches pages against the provided term and return an ElasticsearchCollection
     * of Page entity.
     *
     * @param  string                   $term
     * @param  integer                  $start
     * @param  integer                  $limit
     * @return ElasticsearchCollection
     */
    public function searchPage($term, $start = 0, $limit = 25, array $sort = [])
    {
        return $this->customSearchPage([
            'query' => [
                'bool' => [
                    'should' => [
                        [ 'match' => ['title' => ['query' => $term, 'boost' => 2] ] ],
                        [ 'match' => ['title.raw' => ['query' => $term, 'boost' => 2] ] ],
                        [ 'match' => ['title.folded' => ['query' => $term, 'boost' => 2] ] ],
                        [ 'match' => ['tags' => $term] ],
                        [ 'match' => ['tags.raw' => $term] ],
                        [ 'match' => ['contents' => $term] ],
                        [ 'match_phrase_prefix' => ['title' => ['query' => $term, 'boost' => 2] ] ],
                        [ 'match_phrase_prefix' => ['title.raw' => ['query' => $term, 'boost' => 2] ] ],
                        [ 'match_phrase_prefix' => ['title.folded' => ['query' => $term, 'boost' => 2] ] ],
                        [ 'match_phrase_prefix' => ['tags' => $term] ],
                    ],
                ],
            ],
        ], $start, $limit, $sort);
    }

    /**
     * Searches pages against the provided body.
     *
     * @param  array   $body
     * @param  integer $start
     * @param  integer $limit
     * @param  array   $sort
     * @return ElasticsearchCollection
     */
    public function customSearchPage(array $body, $start = 0, $limit = 25, array $sort = [], $formatResult = true)
    {
        $criteria = [
            'index' => $this->getIndexName(),
            'type'  => $this->getPageTypeName(),
            'from'  => (int) $start,
            'size'  => (int) $limit,
            'body'  => $body,
        ];

        if (false != $sort) {
            $criteria['sort'] = $sort;
        }

        $result = $this->getClient()->search($criteria);

        $pages = $result['hits']['hits'];
        if ($formatResult) {
            $uids = array_column($pages, '_id');
            if (false != $uids) {
                $pages = $this->sortEntitiesByUids(
                    $uids,
                    $this->entyMgr->getRepository(Page::class)->findBy(['_uid' => $uids])
                );
            }
        }

        return new ElasticsearchCollection($pages, $result['hits']['total'], $start, $limit);
    }

    /**
     * Delete the provided tag from Elasticsearch's index.
     *
     * @param  Tag  $tag
     * @return self
     */
    public function deleteTag(Tag $tag)
    {
        $this->getClient()->delete([
            'index' => $this->getIndexName(),
            'type'  => $this->getTagTypeName(),
            'id'    => $tag->getUid(),
        ]);

        return $this;
    }

    /**
     * Searches tag with the provided prefix. If `prefix == false`, it will
     * returns all tags with pagination.
     *
     * Note that tags are ordered by its name (ascending).
     *
     * @param  string $prefix
     * @param  integer $start
     * @param  integer $limit
     * @return ElasticsearchCollection
     */
    public function searchTag($prefix, $start, $limit)
    {
        $filter = [
            'match_all' => new \stdClass,
        ];
        if (false != $prefix && is_string($prefix)) {
            $filter = [
                'prefix' => [
                    'name' => strtolower($prefix),
                ],
            ];
        }

        try {
            $result = $this->getClient()->search([
                'index' => $this->getIndexName(),
                'type'  => $this->getTagTypeName(),
                'from'  => (int) $start,
                'size'  => (int) $limit,
                'body'  => [
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
            ]);
        } catch (\Exception $e) {
            error_log($e->getMessage());

            return new ElasticsearchCollection([], 0);
        }

        $uids = [];
        foreach ($result['hits']['hits'] as $document) {
            $uids[] = $document['_id'];
        }

        $tags = [];
        if (false != $uids) {
            $tags = $this->sortEntitiesByUids(
                $uids,
                $this->entyMgr->getRepository(Tag::class)->findBy(['_uid' => $uids])
            );
        }

        return new ElasticsearchCollection($tags, $result['hits']['total']);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer)
    {
        $this->createIndex();
        $this->createTypes();

        $writer->write('  <c2>> Reindexing all pages...</c2>');
        $this->indexAllPages(true);

        $writer->write('  <c2>> Reindexing all tags...</c2>');
        $this->indexAllTags();

        $writer->write('');
        $writer->write('Contents are now reindexed into Elasticsearch.');
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job)
    {
        return $job instanceof ElasticsearchJob;
    }

    /**
     * Sorts the provided collection by the order defined in the uids array.
     *
     * Note that provided entities collection must have the method ::getUid().
     *
     * @param  array  $uids
     * @param  array  $entities
     * @return array
     */
    protected function sortEntitiesByUids(array $uids, array $entities)
    {
        $sorted = [];
        $positions = array_flip($uids);
        foreach ($entities as $entity) {
            $sorted[$positions[$entity->getUid()]] = $entity;
        }

        ksort($sorted);

        return array_values($sorted);
    }

    protected function extractTitleFromPage(Page $page)
    {
        return $page->getTitle();
    }

    protected function extractAbstractUidFromPage(Page $page)
    {
        $contentUids = $this->contentMgr->getUidsFromPage($page, $this->bbtoken);
        $abstract = $this->getRealFirstContentByUid(
            $this->entyMgr->getRepository(ArticleAbstract::class)->findBy([
                '_uid' => $contentUids,
            ]),
            $contentUids
        );

        $abstract = $abstract ?: null;
        if (null === $abstract) {
            $abstract = $this->getRealFirstContentByUid(
                $this->entyMgr->getRepository(Paragraph::class)->findBy([
                    '_uid' => $contentUids,
                ]),
                $contentUids
            );
        }

        return $abstract ? $abstract->getUid() : null;
    }

    protected function extractTextsFromPage(Page $page)
    {
        $contentUids = $this->contentMgr->getUidsFromPage($page, $this->bbtoken);

        $titles = array_merge(
            $this->entyMgr->getRepository(Title::class)->findBy([
                '_uid' => $contentUids,
            ]),
            $this->entyMgr->getRepository(ArticleTitle::class)->findBy([
                '_uid' => $contentUids,
            ])
        );

        $result = [];
        foreach ($titles as $title) {
            $title->setDraft(null);
            $result[] = $title->value;
        }

        $abstracts = $this->entyMgr->getRepository(ArticleAbstract::class)->findBy([
            '_uid' => $contentUids,
        ]);

        foreach ($abstracts as $abstract) {
            $abstract->setDraft(null);
            $result[] = $abstract->value;
        }

        $paragraphs = $this->entyMgr->getRepository(Paragraph::class)->findBy([
            '_uid' => $contentUids,
        ]);

        foreach ($paragraphs as $paragraph) {
            $paragraph->setDraft(null);
            $result[] = $paragraph->value;
        }

        return $this->cleanText(implode(' ', $result));
    }

    protected function extractImageUidFromPage(Page $page)
    {
        $contentUids = $this->contentMgr->getUidsFromPage($page, $this->bbtoken);
        $media = $this->getRealFirstContentByUid(
            array_merge(
                $this->entyMgr->getRepository(Image::class)->findBy([
                    '_uid' => $contentUids,
                ]),
                $this->entyMgr->getRepository(Video::class)->findBy([
                    '_uid' => $contentUids,
                ])
            ),
            $contentUids
        );

        if ($media instanceof Video) {
            $media = $media->thumbnail;
            if (false == $media->image->path || AbstractClassContent::STATE_NORMAL !== $media->getState()) {
                $contentUids = array_filter($contentUids, function ($uid) use ($media) {
                    return $uid !== $media->getUid();
                });
                $media = $this->getRealFirstContentByUid(
                    $this->entyMgr->getRepository(Image::class)->findBy([
                        '_uid' => $contentUids,
                    ]),
                    $contentUids
                );
            }
        }

        return $media ? $media->getUid() : null;
    }

    protected function cleanText($text)
    {
        return trim(preg_replace('#\s\s+#', ' ', preg_replace('#<[^>]+>#', ' ', $text)));
    }

    protected function getRealFirstContentByUid(array $contents, array $orders)
    {
        if (false == $contents) {
            return null;
        }

        $firstContent = array_pop($contents);
        $curPos = array_search($firstContent->getUid(), $orders);
        foreach ($contents as $content) {
            if ($curPos > $pos = array_search($content->getUid(), $orders)) {
                $curPos = $pos;
                $firstContent = $content;
            }
        }

        return $firstContent;
    }
}
