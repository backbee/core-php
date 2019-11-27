<?php

namespace BackBeeCloud\Entity;

use BackBee\ClassContent\Revision;
use BackBee\NestedNode\Repository\PageRepository;
use BackBee\Security\Token\BBUserToken;
use BackBee\Site\Layout;
use BackBee\Site\Site;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBee\BBApplication;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\MetaData\MetaData;
use BackBee\MetaData\MetaDataBag;
use BackBee\NestedNode\KeyWord;
use BackBee\NestedNode\Page;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\PageCategory\PageCategory;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeManager;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\TransactionRequiredException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Exception;
use InvalidArgumentException;
use LogicException;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageManager
{
    /**
     * @var BBUserToken|null
     */
    protected $bbToken;

    /**
     * @var EntityManager
     */
    protected $entityMgr;

    /**
     * @var TypeManager
     */
    protected $typeMgr;

    /**
     * @var ContentManager
     */
    protected $contentMgr;

    /**
     * @var PageRepository
     */
    protected $repository;

    /**
     * @var ElasticsearchManager
     */
    protected $elsMgr;

    /**
     * @var TagManager
     */
    protected $tagMgr;

    /**
     * @var bool
     */
    protected $hydratePage = true;

    /**
     * @var null|Page
     */
    protected $currentPage;

    /**
     * @var MultiLangManager
     */
    protected $multiLangMgr;

    /**
     * @var null|Lang
     */
    protected $currentLang;

    /**
     * @var PageCategoryManager
     */
    protected $pageCategoryManager;

    /**
     * PageManager constructor.
     *
     * @param BBApplication $app
     */
    public function __construct(BBApplication $app)
    {
        $this->bbToken = $app->getBBUserToken();
        $this->entityMgr = $app->getEntityManager();
        $this->typeMgr = $app->getContainer()->get('cloud.page_type.manager');
        $this->contentMgr = $app->getContainer()->get('cloud.content_manager');
        $this->repository = $this->entityMgr->getRepository(Page::class);
        $this->elsMgr = $app->getContainer()->get('elasticsearch.manager');
        $this->tagMgr = $app->getContainer()->get('cloud.tag_manager');
        $this->multiLangMgr = $app->getContainer()->get('multilang_manager');
        $this->pageCategoryManager = $app->getContainer()->get('cloud.page_category.manager');
    }

    /**
     * Enables page hydratation on new page creation.
     */
    public function enablePageHydratation()
    {
        $this->hydratePage = true;
    }

    /**
     * Disables page hydratation on new page creation.
     */
    public function disablePageHydratation()
    {
        $this->hydratePage = false;
    }

    /**
     * Returns current page, which means page that is currently creating or
     * duplicating. Otherwise null is returned.
     *
     * @return null|Page
     */
    public function getCurrentPage(): ?Page
    {
        return $this->currentPage;
    }

    /**
     * Returns page count of current application
     *
     * @return int
     * @throws QueryException
     */
    public function count(): int
    {
        return (int) $this->repository->createQueryBuilder('p')
            ->select('count(p)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns formatted data of provided page.
     *
     * @param Page $page            The page to format
     * @param bool $strictDraftMode If false, it will perform additional check
     *                              on global contents to determine if page is drafted
     *
     * @return array
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function format(Page $page, $strictDraftMode = false): array
    {
        $pageSeo = $this->getPageSeoMetadata($page);
        $type = $this->typeMgr->findByPage($page);
        $result = $this->elsMgr->getClient()->get([
            'id'      => $page->getUid(),
            'index'   => $this->elsMgr->getIndexName(),
            'type'    => $this->elsMgr->getPageTypeName(),
            '_source' => ['has_draft_contents'],
        ]);

        $isDrafted = $result['found'] && isset($result['_source']['has_draft_contents'])
            ? $result['_source']['has_draft_contents']
            : false;

        if (false === $strictDraftMode && !$isDrafted) {
            $isDrafted = $this->contentMgr->hasGlobalContentDraft();
        }

        return [
            'id' => $page->getUid(),
            'title' => $page->getTitle(),
            'type' => $type ? $type->uniqueName() : null,
            'type_data' => $type,
            'category' => $this->pageCategoryManager->getCategoryByPage($page),
            'is_online' => $page->isOnline(),
            'is_drafted' => $isDrafted,
            'url' => $page->getUrl(),
            'tags' => array_map(static function (KeyWord $keyword) {
                return [
                    'uid' => $keyword->getUid(),
                    'label' => $keyword->getKeyWord(),
                ];
            }, $this->getPageTag($page)->getTags()->toArray()),
            'seo' => $pageSeo,
            'lang' => $this->multiLangMgr->getLangByPage($page),
            'created_at' => $page->getCreated()->format('Y-m-d H:i:s'),
            'modified' => $page->getModified()->format('Y-m-d H:i:s'),
            'published_at' => $page->getPublishing()
                ? $page->getPublishing()->format('Y-m-d H:i:s')
                : null,
        ];
    }

    /**
     * Applies ::format() on every item of the provided collection.
     *
     * @param mixed $collection
     * @param bool  $strictDraftMode
     *
     * @return array
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @see ::format()
     *
     */
    public function formatCollection($collection, $strictDraftMode): array
    {
        $result = [];
        foreach ($collection as $page) {
            $result[] = $this->format($page, $strictDraftMode);
        }

        return $result;
    }

    /**
     * Returns an instance of Page if provided uid exists else null.
     *
     * @param  string $uid The requested page uid
     * @return Page|null
     */
    public function get($uid): ?Page
    {
        return $this->repository->find($uid);
    }

    /**
     * Returns every page ordered by modified date (desc) of current application.
     *
     * @param array $criteria
     * @param       $start
     * @param       $limit
     * @param array $sort
     *
     * @return array|ElasticsearchCollection|Paginator
     */
    public function getBy(array $criteria = [], $start, $limit, array $sort = [])
    {
        $lang = $criteria['lang'] ?? null;
        if (null === $lang && isset($criteria['page_uid'])) {
            $page = $this->get($criteria['page_uid']);
            $lang = $page ? $this->multiLangMgr->getLangByPage($page) : null;
            unset($criteria['lang']);
        }

        $tags = [];
        $rawTags = (isset($criteria['tags']) && !empty($criteria['tags']) ? explode(',', $criteria['tags']) : []);
        if (!empty($rawTags)) {
            foreach ($rawTags as $tagId) {
                if (null !== $tag = $this->entityMgr->getRepository(KeyWord::class)->find($tagId)) {
                    $tags[] = $tag;
                }
            }
        } else {
            unset($criteria['tags']);
        }

        if (null === ($criteria['category'] ?? null)) {
            unset($criteria['category']);
        }

        unset($criteria['page_uid']);

        if (0 === count($criteria) || (1 === count($criteria) && isset($criteria['title'])) || isset($criteria['has_draft_only'])) {
            $query = [
                'query' => [
                    'bool' => [
                        'must'   => [],
                        'should' => [],
                    ],
                ],
            ];

            if ($this->multiLangMgr->isActive()) {
                $query['query']['bool']['must_not'] = [
                    ['match' => ['url' => '/']],
                ];
            }

            if (null !== ($criteria['category'] ?? null)) {
                $query['query']['bool']['must'] = ['match' => ['category' => $criteria['category']]];
            }

            if (null !== ($criteria['type'] ?? null)) {
                $query['query']['bool']['must'] = [['match' => ['type' => $criteria['type']]]];
            }

            if (null !== ($criteria['is_online'] ?? null) && 'all' !== $criteria['is_online']) {
                $query['query']['bool']['must'][] = ['match' => ['is_online' => (bool) $criteria['is_online']]];
            }

            if (!empty($tags)) {
                $query['query']['bool']['must'] = array_map(static function (KeyWord $tag) {
                    return ['term' => ['tags.raw' => strtolower($tag->getKeyWord())]];
                }, $tags);
            }

            if ($lang && $lang !== 'all') {
                $query['query']['bool']['must'][] = ['prefix' => ['url' => sprintf('/%s/', $lang)]];
            }

            if (isset($criteria['title'])) {
                $title = str_replace('%', '', $criteria['title']);
                $query['query']['bool']['should'] = [
                    ['match' => ['title' => $title]],
                    ['match' => ['title.raw' => $title]],
                    ['match' => ['title.folded' => $title]],
                    ['match_phrase_prefix' => ['title' => $title]],
                    ['match_phrase_prefix' => ['title.raw' => $title]],
                    ['match_phrase_prefix' => ['title.folded' => $title]],
                    ['match_phrase_prefix' => ['tags' => $title]],
                ];
                $query['query']['bool']['minimum_should_match'] = 1;
            }

            if (isset($criteria['has_draft_only']) ? (bool) $criteria['has_draft_only'] : false) {
                $query['query']['bool']['must'][] = ['match' => ['has_draft_contents' => true]];
            }

            $sortValidAttrNames = [
                'modified_at',
                'created_at',
                'published_at',
                'type',
                'is_online',
                'category',
            ];
            $sortValidOrder = ['asc', 'desc'];

            $formattedSort = [];
            $sort = 0 === count($criteria) && false !== $sort ? $sort : ['modified_at' => 'desc'];
            foreach ($sort as $attr => $order) {
                if (!in_array($attr, $sortValidAttrNames, true)) {
                    throw new InvalidArgumentException(sprintf('Pages are not sortable by %s .', $attr));
                }
                if (!in_array($order, $sortValidOrder, true)) {
                    throw new InvalidArgumentException(sprintf("'%s' is not a valid order direction.", $order));
                }
                $formattedSort[] = $attr . ':' . $order;
            }

            return $this->elsMgr->customSearchPage($query, $start, $limit, $formattedSort);
        }

        unset($criteria['page_uid']);
        if (1 === count($criteria) && isset($criteria['title'])) {
            return $this->elsMgr->searchPage(str_replace('%', '', $criteria['title']), $start, $limit);
        }

        $qb = $this
            ->repository
            ->createQueryBuilder('p')
            ->where('p._state != :deleted_state')
            ->setParameter('deleted_state', Page::STATE_DELETED)
            ->orderBy('p._modified', 'desc')
            ->setFirstResult($start)
            ->setMaxResults($limit);

        if ($this->multiLangMgr->isActive()) {
            $qb
                ->andWhere($qb->expr()->neq('p._url', ':url'))
                ->setParameter('url', '/');
        }

        try {
            foreach ($criteria as $attr => $data) {
                $method = 'filterBy' . implode('', array_map('ucfirst', explode('_', $attr)));
                if (method_exists($this, $method)) {
                    $this->{$method}($qb, $data);
                } else {
                    throw new InvalidArgumentException("`$attr` attribute is not filterable");
                }
            }
        } catch (EmptyPageSelectionException $e) {
            return [];
        }

        return new Paginator($qb->getQuery(), false);
    }

    /**
     * Creates and persist a new page according to provided data.
     *
     * @param array $data
     *
     * @return Page
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws QueryException
     * @throws TransactionRequiredException
     * @throws \BackBee\Exception\InvalidArgumentException
     */
    public function create(array $data): Page
    {
        $data = $this->prepareDataWithLang($data);
        $uid = $data['uid'] ?? null;

        $page = new Page($uid);

        $page->setSite($this->getSite());
        $page->setLayout($this->getLayout());
        $page->setParent($this->getRootPage());
        $page->setState(Page::STATE_OFFLINE);
        $page->setPosition($this->count() + 1);

        unset($data['is_online'], $data['uid']);

        $this->entityMgr->persist($page);
        $data['seo'] = $data['seo'] ?? [];
        $data['type'] = $data['type'] ?? $this->typeMgr->getDefaultType()->uniqueName();

        $redirections = $data['redirections'] ?? null;
        unset($data['redirections']);

        $this->update($page, ['title' => $data['title'] ?? ''], false);
        unset($data['title']);
        $this->update($page, ['type' => $data['type']], false);
        unset($data['type']);

        $this->currentPage = $page;
        $this->update($page, $data);

        if (null !== $redirections) {
            $this->handleRedirections($page, (array) $redirections);
        }

        $this->elsMgr->indexPage($page);
        $this->currentPage = null;

        return $page;
    }

    /**
     * @param Page  $page    The page to update
     * @param array $data    The data to use to update the page
     * @param bool  $doFlush If true, PageManager will invoke EntityManager::flush()
     *
     * @throws OptimisticLockException
     */
    public function update(Page $page, array $data, $doFlush = true): void
    {
        $autoUpdateModified = !isset($data['modified_at']);

        foreach ($data as $attr => $value) {
            $setter = 'runSet' . implode('', array_map('ucfirst', explode('_', $attr)));
            if (method_exists($this, $setter)) {
                $this->{$setter}($page, $value);
                if ($autoUpdateModified) {
                    $page->setModified(new DateTime());
                }
            } else {
                throw new InvalidArgumentException(
                    "`$attr` attribute does not exist or is not allowed to be changed"
                );
            }
        }

        if (true === $doFlush) {
            $this->entityMgr->flush();
        }
    }

    /**
     * Executes hard delete on provided page.
     *
     * @param Page $page
     *
     * @throws OptimisticLockException
     */
    public function delete(Page $page): void
    {
        if ($page->isRoot()) {
            throw new LogicException('Home page cannot be deleted');
        }

        $this->repository->deletePage($page);
        $this->entityMgr->flush();
    }

    /**
     * Duplicates the provided page with new title and data. This method also
     * duplicate all contents and maintain the page type association.
     *
     * @param Page  $page
     * @param array $data
     *
     * @return Page
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws QueryException
     * @throws TransactionRequiredException
     * @throws \BackBee\Exception\InvalidArgumentException
     */
    public function duplicate(Page $page, array $data): Page
    {
        $this->entityMgr->beginTransaction();

        $data = $this->prepareDataWithLang($data);
        $data['title'] = $data['title'] ?? '';
        $data['tags'] = isset($data['tags']) ? (array) $data['tags'] : [];
        $putContentOnline = isset($data['put_content_online']) ? (bool) $data['put_content_online'] : false;
        unset($data['type'], $data['is_online'], $data['put_content_online']);

        $copy = new Page();

        $copy->setSite($this->getSite());
        $copy->setLayout($this->getLayout());
        $copy->setParent($this->getRootPage());
        $copy->setState(Page::STATE_OFFLINE);
        $copy->setPosition($this->count() + 1);

        $this->entityMgr->persist($copy);
        $this->typeMgr->associate($copy, $this->typeMgr->findByPage($page));

        $this->update($copy, ['title' => $data['title']], false);
        unset($data['title']);

        $this->currentPage = $copy;
        $this->update($copy, $data, false);

        $this->entityMgr->flush();

        $copy->setContentset($this->contentMgr->duplicateContent(
            $page->getContentSet(),
            $this->bbToken,
            null,
            $putContentOnline
        ));

        $this->entityMgr->flush();
        $this->entityMgr->commit();

        $this->elsMgr->indexPage($copy);
        $this->currentPage = null;

        return $copy;
    }

    /**
     * Returns all pages with draft contents.
     *
     * @return ElasticsearchManager
     */
    public function getPagesWithDraftContents(): ElasticsearchManager
    {
        return $this->elsMgr->customSearchPage([
            'query' => [
                'bool' => [
                    'must' => [
                        ['match' => ['has_draft_contents' => true]],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param Page $page
     *
     * @return array
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     */
    public function getPageSeoMetadata(Page $page): array
    {
        $seoData = [];

        $metadatabag = $page->getMetaData() ?: [];
        foreach ($metadatabag as $attr => $metadata) {
            if (($metadata->getAttribute('name') === $attr) && $value = $metadata->getAttribute('content')) {
                $seoData[$attr] = $value;
            }
        }

        $elasticSearchResult = null;

        try {
            $elasticSearchResult = $this->elsMgr->getClient()->get([
                'id'    => $page->getUid(),
                'type'  => $this->elsMgr->getPageTypeName(),
                'index' => $this->elsMgr->getIndexName(),
                '_source' => ['title', 'abstract_uid', 'type', 'image_uid'],
            ]);
        } catch (Missing404Exception $e) {
            return $seoData;
        }

        $elasticSearchResult = $elasticSearchResult['_source'];
        if (!isset($seoData['title'])) {
            $seoData['title'] = $elasticSearchResult['title'];
        }

        if (
            !isset($seoData['description'])
            && $elasticSearchResult['abstract_uid']
            && $abstract = $this->entityMgr->find(ArticleAbstract::class, $elasticSearchResult['abstract_uid'])
        ) {
            if (strlen($abstract->value) > 300) {
                $seoData['description'] = mb_substr($abstract->value, 0, 300) . '...';
            } else {
                $seoData['description'] = $abstract->value;
            }
            $seoData['description'] = strip_tags(str_replace("\n", '', str_replace('&nbsp;', '', $seoData['description'])));
        }

        if ('article' !== $elasticSearchResult['type']) {
            return $seoData;
        }

        if (
            $elasticSearchResult['image_uid']
            && $image = $this->entityMgr->find(Image::class, $elasticSearchResult['image_uid'])
        ) {
            $seoData['image_url'] = $image->image->path;
        }

        return $seoData;
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $value
     */
    protected function filterByTitle(QueryBuilder $qb, string $value)
    {
        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $rootAlias = current($qb->getRootAliases());
        $qb
            ->{$method}($qb->expr()->like("{$rootAlias}._title", ':title'))
            ->setParameter('title', '%'.$value.'%');
    }

    /**
     * Adds where clause to query builder to filter page collection by state attribute.
     *
     * @param  QueryBuilder $qb
     * @param  bool         $value
     */
    protected function filterByIsOnline(QueryBuilder $qb, bool $value)
    {
        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $rootAlias = current($qb->getRootAliases());
        $qb
            ->{$method}("{$rootAlias}._state = :state")
            ->setParameter('state', $value ? Page::STATE_ONLINE : Page::STATE_OFFLINE);
    }

    /**
     * Adds where clause to query builder to filter page collection by tags.
     *
     * Note that it's an 'AND' condition between each tag.
     *
     * @param  QueryBuilder $qb
     * @param  string       $tags
     * @throws EmptyPageSelectionException if there is at least one tag that does not exist
     *                                     or if there is no page that match the set of requested tags
     */
    protected function filterByTags(QueryBuilder $qb, $tags)
    {
        $keywords = [];
        foreach (explode(',', $tags) as $tagId) {
            if (null === $keyword = $this->entityMgr->getRepository(KeyWord::class)->find($tagId)) {
                throw new EmptyPageSelectionException('');
            }

            $keywords[] = $keyword;
        }

        $pageTags = [];
        foreach ($keywords as $keyword) {
            $tagQb = $this->entityMgr
                ->getRepository(PageTag::class)
                ->createQueryBuilder('pt')
                ->join('pt.page', Page::class)
                ->join('pt.tags', 't')
                ->where('t._uid = :keyword')
                ->setParameter('keyword', $keyword);

            if (0 < count($pageTags)) {
                $tagQb
                    ->orWhere($tagQb->expr()->in('pt.id', ':pagetags'))
                    ->setParameter('pagetags', array_filter($pageTags));
            }

            $pageTags = $tagQb->getQuery()->getResult();
            if (0 === count($pageTags)) {
                throw new EmptyPageSelectionException('');
            }
        }

        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $rootAlias = current($qb->getRootAliases());
        $qb->{$method}($qb->expr()->in("{$rootAlias}._uid", array_map(static function (PageTag $pageTag) {
            return $pageTag->getPage()->getUid();
        }, $pageTags)));
    }

    /**
     * Adds where clause to query builder to filter page collection by page type.
     *
     * @param QueryBuilder $qb
     * @param              $types
     */
    protected function filterByType(QueryBuilder $qb, $types)
    {
        $uniqueNames = [];
        foreach (explode(',', $types) as $type) {
            if (null === $this->typeMgr->find($type)) {
                throw new InvalidArgumentException("`{$type}` is an invalid page type unique name");
            }

            $uniqueNames[] = $type;
        }

        $pageTypes = $this->entityMgr
            ->getRepository(PageType::class)
            ->createQueryBuilder('pt')
            ->join('pt.page', Page::class)
            ->where($qb->expr()->in('pt.typeName', $uniqueNames))
            ->getQuery()
            ->getResult();

        if (0 === count($pageTypes)) {
            throw new EmptyPageSelectionException('');
        }

        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $rootAlias = current($qb->getRootAliases());
        $qb->{$method}($qb->expr()->in("{$rootAlias}._uid", array_map(static function (PageType $pageType) {
            return $pageType->getPage()->getUid();
        }, $pageTypes)));
    }

    /**
     * Adds where clause to query builder to filter page collection by page lang.
     *
     * @param QueryBuilder $qb
     * @param string       $lang
     */
    protected function filterByLang(QueryBuilder $qb, string $lang)
    {
        $langEntity = $this->entityMgr->getRepository(Lang::class)->findOneBy(['lang' => $lang]);

        if ($langEntity instanceof Lang) {
            $pageLang = $this->entityMgr
                ->getRepository(PageLang::class)
                ->createQueryBuilder('pl')
                ->join('pl.page', Page::class)
                ->where('pl.lang = :langId')
                ->setParameter('langId', $langEntity)
                ->getQuery()
                ->getResult();

            if (0 === count($pageLang)) {
                throw new EmptyPageSelectionException('');
            }

            $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
            $rootAlias = current($qb->getRootAliases());
            $qb->{$method}($qb->expr()->in("{$rootAlias}._uid", array_map(static function (PageLang $pageLang) {
                return $pageLang->getPage()->getUid();
            }, $pageLang)));
        }
    }

    /**
     * Adds where clause to query builder to filter page collection by page category.
     *
     * @param QueryBuilder $qb
     * @param string       $category
     */
    protected function filterByCategory(QueryBuilder $qb, string $category)
    {
        $qbPageCategory = $this
            ->entityMgr
            ->getRepository(PageCategory::class)
            ->createQueryBuilder('pc')
            ->join('pc.page', Page::class);

        if ('none' !== $category) {
            $qbPageCategory->where('pc.category = :category')->setParameter('category', $category);
        }

        $pageCategory = $qbPageCategory->getQuery()->getResult();

        if (0 === count($pageCategory)) {
            throw new EmptyPageSelectionException('');
        }

        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $exprMethod = 'none' !== $category ? 'in' : 'notIn';
        $rootAlias = current($qb->getRootAliases());
        $qb->{$method}($qb->expr()->{$exprMethod}(
            "{$rootAlias}._uid",
            array_map(static function (PageCategory $pageCategory) {
                return $pageCategory->getPage()->getUid();
            }, $pageCategory)
        ));
    }

    /**
     * @param  Page   $page  The page to update
     * @param  string $value The value to set
     * @throws InvalidArgumentException if provided value is not string or
     *                                   does not contain at least 2 characters
     */
    protected function runSetTitle(Page $page, $value)
    {
        if (!is_string($value) || 2 > strlen($value)) {
            throw new InvalidArgumentException(
                '`title` must be type of string and contain at least 2 characters'
            );
        }

        $page->setTitle($value);
    }

    /**
     * @param  Page   $page  The page to update
     * @param  string $value The value to set
     * @throws {@see ::genericRunSetDateTime}
     */
    protected function runSetPublishedAt(Page $page, $value)
    {
        $this->genericRunSetDateTime($page, 'setPublishing', $value);
    }

    /**
     * @param  Page   $page  The page to update
     * @param  string $value The value to set
     * @throws {@see ::genericRunSetDateTime}
     */
    protected function runSetCreatedAt(Page $page, $value)
    {
        $this->genericRunSetDateTime($page, 'setCreated', $value);
    }

    /**
     * @param  Page   $page  The page to update
     * @param  string $value The value to set
     * @throws {@see ::genericRunSetDateTime}
     */
    protected function runSetModifiedAt(Page $page, $value)
    {
        $this->genericRunSetDateTime($page, 'setModified', $value);
    }

    /**
     * Generic method to set datetime for page.
     *
     * @param  Page   $page   the page to update
     * @param  string $method the method to call
     * @param  string $value
     * @throws InvalidArgumentException if the provided value is not a valid datetime string
     */
    protected function genericRunSetDateTime(Page $page, $method, $value): void
    {
        $datetime = null;

        try {
            $datetime = new DateTime($value);
        } catch (Exception $e) {
            throw new InvalidArgumentException(sprintf(
                '[%s - %s] Failed to create an instance of DateTime, "%s" is not valid.',
                __METHOD__,
                $method,
                $value
            ));
        }

        $page->$method($datetime);
    }

    /**
     * @param Page $page  The page to update
     * @param bool $value The value to set
     *
     * @throws Exception
     */
    protected function runSetIsOnline(Page $page, $value)
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('`is_online` must be type of boolean');
        }
        $today = new DateTime();
        try {
            $page->setState($value ? Page::STATE_ONLINE : Page::STATE_OFFLINE);
            if (true === $value && !$page->isRoot()) {
                $page->setPublishing($today);
            }
            if (false === $value && !$page->isRoot() && $page->getPublishing() <= $today) {
                $page->setPublishing();
            }
        } catch (LogicException $e) {
            throw new LogicException('Home page cannot be offline');
        }
    }

    /**
     * @param  Page   $page
     * @param  mixed $values
     */
    protected function runSetTags(Page $page, $values)
    {
        $pageTag = $this->getPageTag($page);

        $pageTag->resetTags();
        foreach ((array) $values as $tagData) {
            $tag = null;
            if (isset($tagData['uid'])) {
                if (null === $tag = $this->tagMgr->get($tagData['uid'])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Cannot find tag with provided uid (:%s)',
                            $tagData['uid']
                        )
                    );
                }
            } else {
                $tag = $this->tagMgr->createIfNotExists($tagData['label']);
            }

            $pageTag->addTag($tag);
        }
    }

    /**
     * @param Page  $page
     * @param array $data
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected function runSetSeo(Page $page, array $data)
    {
        $currentSeo = $this->getPageSeoMetadata($page);
        if (false !== $currentSeo) {
            if (isset($data['title']) && $data['title'] === $currentSeo['title']) {
                unset($data['title']);
            }

            if (isset($data['description']) && $data['description'] === $currentSeo['description']) {
                unset($data['description']);
            }
        }

        if (false === $data) {
            return;
        }

        $bag = $page->getMetaData() ?: new MetaDataBag();

        $data = array_merge([
            'title' => $bag->get('title')
                ? $bag->get('title')->getAttribute('content', '')
                : '',
            'description' => $bag->get('description')
                ? $bag->get('description')->getAttribute('content', '')
                : '',
            'keywords' => $bag->get('keywords')
                ? $bag->get('keywords')->getAttribute('content', '')
                : '',
        ], $data);

        foreach ($data as $name => $value) {
            $metadata = new MetaData($name);
            $metadata->setAttribute('name', $name);
            $metadata->setAttribute('content', $value);
            $bag->add($metadata);
        }

        $page->setMetaData(clone $bag);
    }

    /**
     * @param Page $page
     * @param      $typeName
     */
    protected function runSetType(Page $page, $typeName)
    {
        $type = $this->typeMgr->find($typeName);
        if (null === $type) {
            throw new InvalidArgumentException("You selected `{$typeName}` as page type but it does not exist.");
        }

        $this->typeMgr->associate($page, $type);
        if ($this->hydratePage && $this->entityMgr->getUnitOfWork()->isScheduledForInsert($page)) {
            $this->typeMgr->hydratePageContentsByType($type, $page, $this->bbToken);
        }
    }

    /**
     * @param  Page   $page  The page to update
     * @param  string $value The value to set
     * @throws InvalidArgumentException if provided value is not string or
     *                                   does not contain at least 2 characters
     */
    protected function runSetLang(Page $page, $value)
    {
        if ($this->currentLang instanceof Lang) {
            $this->multiLangMgr->associate($page, $this->currentLang);
            $this->currentLang = null;
        }
    }

    /**
     * @param Page $page
     * @param      $value
     */
    protected function runSetUrl(Page $page, $value)
    {
        $page->setUrl($value);
    }

    /**
     * @param Page $page
     * @param      $value
     */
    protected function runSetCategory(Page $page, $value)
    {
        if (false === $value) {
            return;
        }

        $this->pageCategoryManager->associatePageAndCategory($page, $value);
    }

    /**
     * Create redirection for all URLs in $redirections to provided page's url.
     *
     * @param Page  $page
     * @param array $redirections
     *
     * @throws OptimisticLockException
     */
    protected function handleRedirections(Page $page, array $redirections = []): void
    {
        foreach ($redirections as $redirection) {
            $redirection = '/' . preg_replace('~^/~', '', $redirection);
            $pageRedirection = new PageRedirection($redirection, $page->getUrl());
            $this->entityMgr->persist($pageRedirection);
        }

        $this->entityMgr->flush();
    }

    /**
     * Returns PageTag entity for the given page. If it does not exist, this
     * method will create and persist it into database.
     *
     * @param  Page   $page
     * @return PageTag
     */
    public function getPageTag(Page $page): PageTag
    {
        $pageTag = $this->entityMgr->getRepository(PageTag::class)->findOneBy([
            'page' => $page,
        ]);

        if (null === $pageTag) {
            $pageTag = new PageTag($page);

            $this->entityMgr->persist($pageTag);
        }

        return $pageTag;
    }

    /**
     * @return Layout|null
     */
    protected function getLayout(): ?Layout
    {
        $entity = $this->entityMgr->getRepository(Layout::class)->findOneBy([]);
        return $entity instanceof Layout ? $entity : null;
    }

    /**
     * Returns the current site root page.
     *
     * @return Page
     */
    public function getRootPage(): Page
    {
        $root = null;
        if (null !== $this->currentLang) {
            $root = $this->multiLangMgr->getRootByLang($this->currentLang);
        }

        if (null === $root) {
            $root = $this->entityMgr->getRepository(Page::class)->findOneBy([
                '_url' => '/',
            ]);
        }

        return $root;
    }

    /**
     * Returns the current site.
     *
     * @return Site|null
     */
    protected function getSite(): ?Site
    {
        $entity = $this->entityMgr->getRepository(Site::class)->findOneBy([]);
        return $entity instanceof Site ? $entity : null;
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected function prepareDataWithLang(array $data): array
    {
        if (isset($data['lang'])) {
            $lang = $data['lang'];
            unset($data['lang']);
            $data = ['lang' => $lang] + $data;

            $langEntity = $this->entityMgr->find(Lang::class, $lang);
            if (null === $langEntity || !$langEntity->isActive()) {
                throw new InvalidArgumentException(sprintf(
                    'Lang "%s" does not exist or is not activated',
                    $lang
                ));
            }
        } elseif (null !== $defaultLang = $this->multiLangMgr->getDefaultLang()) {
            $data['lang'] = $defaultLang['id'];
        }

        if (isset($data['lang'])) {
            $this->currentLang = $this->entityMgr->find(Lang::class, $data['lang']);
        }

        return $data;
    }
}
