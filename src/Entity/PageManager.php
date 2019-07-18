<?php

namespace BackBeeCloud\Entity;

use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageRedirection;
use BackBeeCloud\Entity\PageTag;
use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\MetaData\MetaData;
use BackBee\MetaData\MetaDataBag;
use BackBee\NestedNode\KeyWord;
use BackBee\NestedNode\Page;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Elasticsearch\Common\Exceptions\Missing404Exception;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageManager
{
    /**
     * @var \BackBee\Security\Token\BBUserToken|null
     */
    protected $bbtoken;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entyMgr;

    /**
     * @var \BackBeeCloud\PageType\TypeManager
     */
    protected $typeMgr;

    /**
     * @var \BackBeeCloud\Entity\ContentManager
     */
    protected $contentMgr;

    /**
     * @var \BackBee\NestedNode\Repository\PageRepository
     */
    protected $repository;

    /**
     * @var \BackBeeCloud\Elasticsearch\ElasticsearchManager
     */
    protected $elsMgr;

    /**
     * @var \BackBeeCloud\Entity\TagManager
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
     * @var \BackBeeCloud\MultiLangManager
     */
    protected $multilangMgr;

    /**
     * @var null|Lang
     */
    protected $currentLang;

    /**
     * @var \BackBeeCloud\PageCategory\PageCategoryManager
     */
    protected $pageCategoryManager;

    public function __construct(BBApplication $app)
    {
        $this->bbtoken = $app->getBBUserToken();
        $this->entyMgr = $app->getEntityManager();
        $this->typeMgr = $app->getContainer()->get('cloud.page_type.manager');
        $this->contentMgr = $app->getContainer()->get('cloud.content_manager');
        $this->repository = $this->entyMgr->getRepository(Page::class);
        $this->elsMgr = $app->getContainer()->get('elasticsearch.manager');
        $this->tagMgr = $app->getContainer()->get('cloud.tag_manager');
        $this->multilangMgr = $app->getContainer()->get('multilang_manager');
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
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Returns page count of current application
     *
     * @return int
     */
    public function count()
    {
        return (int) $this->repository->createQueryBuilder('p')
            ->select('count(p)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Returns formatted data of provided page.
     *
     * @param Page $page            The page to format
     * @param bool $strictDraftMode If false, it will perform additional check
     *                              on global contents to determine if page is drafted
     *
     * @return array
     */
    public function format(Page $page, $strictDraftMode = false)
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
            'tags' => array_map(function(KeyWord $keyword) {
                return [
                    'uid' => $keyword->getUid(),
                    'label' => $keyword->getKeyWord(),
                ];
            }, $this->getPageTag($page)->getTags()->toArray()),
            'seo' => $pageSeo,
            'lang' => $this->multilangMgr->getLangByPage($page),
            'created_at' => $page->getCreated()->format('Y-m-d H:i:s'),
            'modified' => $page->getModified()->format('Y-m-d H:i:s'),
            'published_at' => $page->getPublishing()
                ? $page->getPublishing()->format('Y-m-d H:i:s')
                : null
            ,
        ];
    }

    /**
     * Applies ::format() on every item of the provided collection.
     *
     * @see ::format()
     *
     * @param mixed $collection
     * @param bool  $strictDraftMode
     *
     * @return array
     */
    public function formatCollection($collection, $strictDraftMode)
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
    public function get($uid)
    {
        return $this->repository->find($uid);
    }

    /**
     * Returns every page ordered by modified date (desc) of current application.
     *
     * @return array
     */
    public function getBy(array $criteria = [], $start, $limit, array $sort = [])
    {
        $hasDraftOnly = isset($criteria['has_draft_only'])
            ? (bool) $criteria['has_draft_only']
            : false
        ;
        unset($criteria['has_draft_only']);

        $lang = isset($criteria['lang']) ? $criteria['lang'] : null;
        if (null === $lang && isset($criteria['page_uid'])) {
            $page = $this->get($criteria['page_uid']);
            $lang = $page ? $this->multilangMgr->getLangByPage($page) : null;
        }

        $tags = [];
        $rawTags = isset($criteria['tags']) ? explode(',', $criteria['tags']) : [];
        foreach ($rawTags as $tag) {
            if (null !== $tag = $this->entyMgr->getRepository(KeyWord::class)->exists($tag)) {
                $tags[] = $tag;
            }
        }

        $category = isset($criteria['category']) ? $criteria['category'] : null;

        unset($criteria['tags'], $criteria['lang'], $criteria['page_uid'], $criteria['category']);
        if (0 === count($criteria) || (1 === count($criteria) && isset($criteria['title']))) {
            $query = [
                'query' => [
                    'bool' => [
                        'must'   => [],
                        'should' => [],
                    ],
                ],
            ];

            if ($this->multilangMgr->isActive()) {
                $query['query']['bool']['must_not'] = [
                    [ 'match' => ['url' => '/' ] ],
                ];
            }

            if ($category) {
                $query['query']['bool']['must'] = [ 'match' => [ 'category' => $category ] ];
            }

            if ($tags) {
                $query['query']['bool']['must'] = array_map(function ($tag) {
                    return [ 'term' => ['tags.raw' => strtolower($tag->getKeyWord())] ];
                }, $tags);
            }

            if ($lang) {
                $query['query']['bool']['must'][] = [ 'prefix' => ['url' => sprintf('/%s/', $lang)] ];
            }

            if (isset($criteria['title'])) {
                $title = str_replace('%', '', $criteria['title']);
                $query['query']['bool']['should'] = [
                    [ 'match' => ['title' => $title] ],
                    [ 'match' => ['title.raw' => $title] ],
                    [ 'match' => ['title.folded' => $title] ],
                    [ 'match_phrase_prefix' => ['title' => $title] ],
                    [ 'match_phrase_prefix' => ['title.raw' => $title] ],
                    [ 'match_phrase_prefix' => ['title.folded' => $title] ],
                    [ 'match_phrase_prefix' => ['tags' => $title] ],
                ];
                $query['query']['bool']['minimum_should_match'] = 1;
            }

            if ($hasDraftOnly) {
                $query['query']['bool']['must'][] = [ 'match' => ['has_draft_contents' => true] ];
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
            $sort = 0 === count($criteria) && false != $sort ? $sort : ['modified_at' => 'desc'];
            foreach ($sort as $attr => $order) {
                if (!in_array($attr, $sortValidAttrNames)) {
                    throw new \InvalidArgumentException(sprintf(
                        "Pages are not sortable by %s .",
                        $attr
                    ));
                }

                if (!in_array($order, $sortValidOrder)) {
                    throw new \InvalidArgumentException(sprintf(
                        "'%s' is not a valid order direction.",
                        $order
                    ));
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
            ->orderBy('p._modified', 'desc')
            ->setFirstResult($start)
            ->setMaxResults($limit)
        ;

        if ($this->multilangMgr->isActive()) {
            $qb
                ->where($qb->expr()->neq('p._url', ':url'))
                ->setParameter('url', '/')
            ;
        }

        try {
            foreach ($criteria as $attr => $data) {
                $method = 'filterBy' . implode('', array_map('ucfirst', explode('_', $attr)));
                if (method_exists($this, $method)) {
                    $this->{$method}($qb, $data);
                } else {
                    throw new \InvalidArgumentException("`$attr` attribute is not filterable");
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
     * @param  array  $data
     * @return Page
     */
    public function create(array $data)
    {
        $data = $this->prepareDataWithLang($data);
        $uid = isset($data['uid']) ? $data['uid'] : null;

        $page = new Page($uid);

        $page->setSite($this->getSite());
        $page->setLayout($this->getLayout());
        $page->setParent($this->getRootPage());
        $page->setState(Page::STATE_OFFLINE);
        $page->setPosition($this->count() + 1);

        unset($data['is_online'], $data['uid']);

        $this->entyMgr->persist($page);
        $data['seo'] = isset($data['seo']) ? $data['seo'] : [];
        $data['type'] = isset($data['type'])
            ? $data['type']
            : $this->typeMgr->getDefaultType()->uniqueName()
        ;

        $redirections = isset($data['redirections']) ? $data['redirections'] : null;
        unset($data['redirections']);

        $this->update($page, ['title' => isset($data['title']) ? $data['title'] : ''], false);
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
     * @param  Page  $page    The page to update
     * @param  array $data    The data to use to update the page
     * @param  bool  $doFlush If true, PageManager will invoke EntityManager::flush()
     * @throws \InvalidArgumentException if we try to do forbidden changes
     */
    public function update(Page $page, array $data, $doFlush = true)
    {
        $autoUpdateModified = !isset($data['modified_at']);

        foreach ($data as $attr => $value) {
            $setter = 'runSet' . implode('', array_map('ucfirst', explode('_', $attr)));
            if (method_exists($this, $setter)) {
                $this->{$setter}($page, $value);
                if ($autoUpdateModified) {
                    $page->setModified(new \DateTime());
                }
            } else {
                throw new \InvalidArgumentException(
                    "`$attr` attribute does not exist or is not allowed to be changed"
                );
            }
        }

        if (true === $doFlush) {
            $this->entyMgr->flush();
        }
    }

    /**
     * Executes hard delete on provided page.
     *
     * @param  Page   $page
     */
    public function delete(Page $page)
    {
        if ($page->isRoot()) {
            throw new \LogicException('Home page cannot be deleted');
        }

        $this->repository->deletePage($page);
        $this->entyMgr->flush();
    }

    /**
     * Duplicates the provided page with new title and data. This method also
     * duplicate all contents and maintain the page type association.
     *
     * @param  Page   $page
     * @param  array  $data
     * @return Page
     */
    public function duplicate(Page $page, array $data)
    {
        $this->entyMgr->beginTransaction();

        $data = $this->prepareDataWithLang($data);
        $data['title'] = isset($data['title']) ? $data['title'] : '';
        $data['tags'] = isset($data['tags']) ? (array) $data['tags'] : [];
        $putContentOnline = isset($data['put_content_online']) ? (bool) $data['put_content_online'] : false;
        unset($data['type'], $data['is_online'], $data['put_content_online']);

        $copy = new Page();

        $copy->setSite($this->getSite());
        $copy->setLayout($this->getLayout());
        $copy->setParent($this->getRootPage());
        $copy->setState(Page::STATE_OFFLINE);
        $copy->setPosition($this->count() + 1);

        $this->entyMgr->persist($copy);
        $this->typeMgr->associate($copy, $this->typeMgr->findByPage($page));

        $this->update($copy, ['title' => $data['title']], false);
        unset($data['title']);

        $this->currentPage = $copy;
        $this->update($copy, $data, false);

        $this->entyMgr->flush();

        $copy->setContentset($this->contentMgr->duplicateContent(
            $page->getContentSet(),
            $this->bbtoken,
            null,
            $putContentOnline
        ));

        $this->entyMgr->flush();
        $this->entyMgr->commit();

        $this->elsMgr->indexPage($copy);
        $this->currentPage = null;

        return $copy;
    }

    /**
     * Returns all pages with draft contents.
     *
     * @return \BackBeeCloud\Elasticsearch\ElasticsearchManager
     */
    public function getPagesWithDraftContents()
    {
        return $this->elsMgr->customSearchPage([
            'query' => [
                'bool' => [
                    'must' => [
                        [ 'match' => ['has_draft_contents' => true] ],
                    ],
                ],
            ],
        ]);
    }

    public function getPageSeoMetadata(Page $page)
    {
        $seoData = [];

        $metadatabag = $page->getMetaData() ?: [];
        foreach ($metadatabag as $attr => $metadata) {
            if ($metadata->getAttribute('name') === $attr) {
                if ($value = $metadata->getAttribute('content')) {
                    $seoData[$attr] = $value;
                }
            }
        }

        $elasticsearchResult = null;

        try {
            $elasticsearchResult = $this->elsMgr->getClient()->get([
                'id'    => $page->getUid(),
                'type'  => $this->elsMgr->getPageTypeName(),
                'index' => $this->elsMgr->getIndexName(),
                '_source' => ['title', 'abstract_uid', 'type', 'image_uid'],
            ]);
        } catch (Missing404Exception $e) {
            return $seoData;
        }

        $elasticsearchResult = $elasticsearchResult['_source'];
        if (!isset($seoData['title'])) {
            $seoData['title'] = $elasticsearchResult['title'];
        }

        if (
            !isset($seoData['description'])
            && $elasticsearchResult['abstract_uid']
            && $abstract = $this->entyMgr->find(ArticleAbstract::class, $elasticsearchResult['abstract_uid'])
        ) {
            if (strlen($abstract->value) > 300 ) {
                $seoData['description'] = substr($abstract->value, 0, 300) . '...';
            } else {
                $seoData['description'] = $abstract->value;
            }
            $seoData['description'] = strip_tags(str_replace("\n", '', str_replace("&nbsp;", '', $seoData['description'])));
        }

        if ('article' !== $elasticsearchResult['type']) {
            return $seoData;
        }

        if (
            $elasticsearchResult['image_uid']
            && $image = $this->entyMgr->find(Image::class, $elasticsearchResult['image_uid'])
        ) {
            $seoData['image_url'] = $image->image->path;
        }

        return $seoData;
    }

    protected function filterByTitle(QueryBuilder $qb, $value)
    {
        $operator = false !== strpos($value, '%') ? 'like' : 'eq';
        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $qb
            ->{$method}($qb->expr()->{$operator}("{$qb->getRootAlias()}._title", ':title'))
            ->setParameter('title', $value)
        ;
    }

    /**
     * Adds where clause to query builder to filter page collection by state attribute.
     *
     * @param  QueryBuilder $qb
     * @param  bool         $value
     */
    protected function filterByIsOnline(QueryBuilder $qb, $value)
    {
        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $qb
            ->{$method}("{$qb->getRootAlias()}._state = :state")
            ->setParameter('state', $value ? Page::STATE_ONLINE : Page::STATE_OFFLINE)
        ;
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
        foreach (explode(',', $tags) as $tag) {
            if (null === $keyword = $this->entyMgr->getRepository(KeyWord::class)->exists($tag)) {
                throw new EmptyPageSelectionException();
            }

            $keywords[] = $keyword;
        }

        $pageTags = [];
        foreach ($keywords as $keyword) {
            $tagQb = $this->entyMgr
                ->getRepository('BackBeeCloud\Entity\PageTag')
                ->createQueryBuilder('pt')
                ->join('pt.tags', 't')
                ->where('t._uid = :keyword')
                ->setParameter('keyword', $keyword)
            ;

            if (0 < count($pageTags)) {
                $tagQb
                    ->orWhere($tagQb->expr()->in('pt.id', ':pagetags'))
                    ->setParameter('pagetags', array_filter($pageTags))
                ;
            }

            $pageTags = $tagQb->getQuery()->getResult();
            if (0 === count($pageTags)) {
                throw new EmptyPageSelectionException();
            }
        }

        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $qb->{$method}($qb->expr()->in("{$qb->getRootAlias()}._uid", array_map(function(PageTag $pageTag) {
            return $pageTag->getPage()->getUid();
        }, $pageTags)));
    }

    /**
     * Adds where clause to query builder to filter page collection by page type.
     *
     * @param  QueryBuilder $qb
     * @param  string       $tags
     * @throws \InvalidArgumentException if one of the provided page type unique name is valid
     * @throws EmptyPageSelectionException if there is no page that match with requested page type
     */
    protected function filterByType(QueryBuilder $qb, $types)
    {
        $uniqueNames = [];
        foreach (explode(',', $types) as $type) {
            if (null === $this->typeMgr->find($type)) {
                throw new \InvalidArgumentException("`{$type}` is an invalid page type unique name");
            }

            $uniqueNames[] = $type;
        }

        $pageTypes = $this->entyMgr
            ->getRepository('BackBeeCloud\Entity\PageType')
            ->createQueryBuilder('pt')
            ->where($qb->expr()->in('pt.typeName',  $uniqueNames))
            ->getQuery()
            ->getResult()
        ;

        if (0 === count($pageTypes)) {
            throw new EmptyPageSelectionException();
        }

        $method = null === $qb->getDQLPart('where') ? 'where' : 'andWhere';
        $qb->{$method}($qb->expr()->in("{$qb->getRootAlias()}._uid", array_map(function(PageType $pageType) {
            return $pageType->getPage()->getUid();
        }, $pageTypes)));
    }

    /**
     * @param  Page   $page  The page to update
     * @param  string $value The value to set
     * @throws \InvalidArgumentException if provided value is not string or
     *                                   does not contain at least 2 characters
     */
    protected function runSetTitle(Page $page, $value)
    {
        if (!is_string($value) || 2 > strlen($value)) {
            throw new \InvalidArgumentException(
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
     * @throws \InvalidArgumentException if the provided value is not a valid datetime string
     */
    protected function genericRunSetDateTime(Page $page, $method, $value)
    {
        $datetime = null;

        try {
            $datetime = new \DateTime($value);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                '[%s - %s] Failed to create an instance of DateTime, "%s" is not valid.',
                __METHOD__,
                $method,
                $value
            ));
        }

        $page->$method($datetime);
    }

    /**
     * @param  Page $page  The page to update
     * @param  bool $value The value to set
     * @throws \InvalidArgumentException if value is not a boolean
     * @throws \LogicException if you try to put the home page offline
     */
    protected function runSetIsOnline(Page $page, $value)
    {
        if (!is_bool($value)) {
            throw new \InvalidArgumentException('`is_online` must be type of boolean');
        }
        $today = new \DateTime();
        try {
            $page->setState($value ? Page::STATE_ONLINE : Page::STATE_OFFLINE);
            if (true === $value && !$page->isRoot()) {
                $page->setPublishing($today);
            }
            if (false === $value && !$page->isRoot() && $page->getPublishing() <= $today) {
                $page->setPublishing();
            }
        } catch (\LogicException $e) {
            throw new \LogicException('Home page cannot be offline');
        }
    }

    /**
     * @param  Page   $page
     * @param  mixed $values
     */
    protected function runSetTags(Page $page, $values)
    {
        $pagetag = $this->getPageTag($page);

        $pagetag->resetTags();
        foreach ((array) $values as $tagData) {
            $tag = null;
            if (isset($tagData['uid'])) {
                if (null === $tag = $this->tagMgr->get($tagData['uid'])) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Cannot find tag with provided uid (:%s)',
                            $tagData['uid']
                        )
                    );
                }
            } else {
                $tag = $this->tagMgr->createIfNotExists($tagData['label']);
            }

            $pagetag->addTag($tag);
        }
    }

    /**
     * @param  Page   $page
     * @param  array  $data
     */
    protected function runSetSeo(Page $page, array $data)
    {
        $currentSeo = $this->getPageSeoMetadata($page);
        if (false != $currentSeo) {
            if (isset($data['title']) && $data['title'] === $currentSeo['title']) {
                unset($data['title']);
            }

            if (isset($data['description']) && $data['description'] === $currentSeo['description']) {
                unset($data['description']);
            }
        }

        if (false == $data) {
            return;
        }

        $bag = $page->getMetaData() ?: new MetaDataBag();

        $data = array_merge([
            'title' => $bag->get('title')
                ? $bag->get('title')->getAttribute('content', '')
                : ''
            ,
            'description' => $bag->get('description')
                ? $bag->get('description')->getAttribute('content', '')
                : ''
            ,
            'keywords' => $bag->get('keywords')
                ? $bag->get('keywords')->getAttribute('content', '')
                : ''
            ,
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
     * @param  Page   $page
     * @param  array  $data
     */
    protected function runSetType(Page $page, $typeName)
    {
        $type = $this->typeMgr->find($typeName);
        if (null === $type) {
            throw new \InvalidArgumentException("You selected `{$typeName}` as page type but it does not exist.");
        }

        $this->typeMgr->associate($page, $type);
        if ($this->hydratePage && $this->entyMgr->getUnitOfWork()->isScheduledForInsert($page)) {
            $this->typeMgr->hydratePageContentsByType($type, $page, $this->bbtoken);
        }
    }

    /**
     * @param  Page   $page  The page to update
     * @param  string $value The value to set
     * @throws \InvalidArgumentException if provided value is not string or
     *                                   does not contain at least 2 characters
     */
    protected function runSetLang(Page $page, $value)
    {
        if ($this->currentLang instanceof Lang) {
            $this->multilangMgr->associate($page, $this->currentLang);
            $this->currentLang = null;
        }
    }

    protected function runSetUrl(Page $page, $value)
    {
        $page->setUrl($value);
    }

    protected function runSetCategory(Page $page, $value)
    {
        if (false == $value) {
            return;
        }

        $this->pageCategoryManager->associatePageAndCategory($page, $value);
    }

    /**
     * Create redirection for all URLs in $redirections to provided page's url.
     *
     * @param  Page  $page
     * @param  array $redirections
     */
    protected function handleRedirections(Page $page, array $redirections = [])
    {
        foreach ($redirections as $redirection) {
            $redirection = '/' . preg_replace('~^/~', '', $redirection);
            $pageRedirection = new PageRedirection($redirection, $page->getUrl());
            $this->entyMgr->persist($pageRedirection);
        }

        $this->entyMgr->flush();
    }

    /**
     * Returns PageTag entity for the given page. If it does not exist, this
     * method will create and persist it into database.
     *
     * @param  Page   $page
     * @return PageTag
     */
    public function getPageTag(Page $page)
    {
        $pageTag = $this->entyMgr->getRepository('BackBeeCloud\Entity\PageTag')->findOneBy([
            'page' => $page,
        ]);

        if (null === $pageTag) {
            $pageTag = new PageTag($page);

            $this->entyMgr->persist($pageTag);
        }

        return $pageTag;
    }

    /**
     * @return \BackBee\Site\Layout
     */
    protected function getLayout()
    {
        return $this->entyMgr->getRepository('BackBee\Site\Layout')->findOneBy([]);
    }

    /**
     * Returns the current site root page.
     *
     * @return Page
     */
    protected function getRootPage()
    {
        $root = null;
        if (null !== $this->currentLang) {
            $root = $this->multilangMgr->getRootByLang($this->currentLang);
        }

        if (null === $root) {
            $root = $this->entyMgr->getRepository(Page::class)->findOneBy([
                '_url' => '/',
            ]);
        }

        return $root;
    }

    /**
     * Returns the current site.
     *
     * @return \BackBee\Site\Site
     */
    protected function getSite()
    {
        return $this->entyMgr->getRepository('BackBee\Site\Site')->findOneBy([]);
    }

    protected function prepareDataWithLang(array $data)
    {
        if (isset($data['lang'])) {
            $lang = $data['lang'];
            unset($data['lang']);
            $data = ['lang' => $lang] + $data;

            $langEntity = $this->entyMgr->find(Lang::class, $lang);
            if (null === $langEntity || !$langEntity->isActive()) {
                throw new \InvalidArgumentException(sprintf(
                    'Lang "%s" does not exist or is not activated',
                    $lang
                ));
            }
        } elseif (null !== $defaultlang = $this->multilangMgr->getDefaultLang()) {
            $data['lang'] = $defaultlang['id'];
        }

        if (isset($data['lang'])) {
            $this->currentLang = $this->entyMgr->find(Lang::class, $data['lang']);
        }

        return $data;
    }


}
