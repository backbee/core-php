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

namespace BackBeeCloud\Entity;

use BackBee\AutoLoader\Exception\ClassNotFoundException;
use BackBee\BBApplication;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Exception\UnknownPropertyException;
use BackBee\KnowledgeGraph\SeoMetadataManager;
use BackBee\MetaData\MetaData;
use BackBee\MetaData\MetaDataBag;
use BackBee\NestedNode\KeyWord;
use BackBee\NestedNode\Page;
use BackBee\NestedNode\Repository\PageRepository;
use BackBee\Security\Token\BBUserToken;
use BackBee\Site\Layout;
use BackBee\Site\Site;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\MultiLang\PageAssociationManager;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeManager;
use BackBeeCloud\SearchEngine\SearchEngineManager;
use BackBeeCloud\Tag\TagManager;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use function is_string;
use function strlen;

/**
 * Class PageManager
 *
 * @package BackBeeCloud\Entity
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
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
     * @var PageAssociationManager
     */
    protected $pageAssociationMgr;

    /**
     * @var SearchEngineManager
     */
    protected $searchEngineManager;

    /**
     * @var SeoMetadataManager
     */
    protected $seoMetadataManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * PageManager constructor.
     *
     * @param BBApplication          $app
     * @param SeoMetadataManager     $seoMetadataManager
     * @param TypeManager            $typeManager
     * @param ContentManager         $contentManager
     * @param ElasticsearchManager   $elasticsearchManager
     * @param TagManager             $tagManager
     * @param MultiLangManager       $multiLangManager
     * @param PageCategoryManager    $pageCategoryManager
     * @param PageAssociationManager $pageAssociationManager
     * @param SearchEngineManager    $searchEngineManager
     * @param LoggerInterface        $logger
     */
    public function __construct(
        BBApplication $app,
        SeoMetadataManager $seoMetadataManager,
        TypeManager $typeManager,
        ContentManager $contentManager,
        ElasticsearchManager $elasticsearchManager,
        TagManager $tagManager,
        MultiLangManager $multiLangManager,
        PageCategoryManager $pageCategoryManager,
        PageAssociationManager $pageAssociationManager,
        SearchEngineManager $searchEngineManager,
        LoggerInterface $logger
    ) {
        $this->bbToken = $app->getBBUserToken();
        $this->entityMgr = $app->getEntityManager();
        $this->typeMgr = $typeManager;
        $this->contentMgr = $contentManager;
        $this->repository = $this->entityMgr->getRepository(Page::class);
        $this->elsMgr = $elasticsearchManager;
        $this->tagMgr = $tagManager;
        $this->multiLangMgr = $multiLangManager;
        $this->pageCategoryManager = $pageCategoryManager;
        $this->pageAssociationMgr = $pageAssociationManager;
        $this->searchEngineManager = $searchEngineManager;
        $this->seoMetadataManager = $seoMetadataManager;
        $this->logger = $logger;
    }

    /**
     * Enables page hydratation on new page creation.
     */
    public function enablePageHydratation(): void
    {
        $this->hydratePage = true;
    }

    /**
     * Disables page hydratation on new page creation.
     */
    public function disablePageHydratation(): void
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
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function count(): int
    {
        return (int)$this->repository->createQueryBuilder('p')
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
     */
    public function format(Page $page, bool $strictDraftMode = false): array
    {
        $pageSeo = $this->seoMetadataManager->getPageSeoMetadata($page);
        $type = $this->typeMgr->findByPage($page);
        $result = $this->elsMgr->getClient()->get(
            [
                'id' => $page->getUid(),
                'index' => $this->elsMgr->getIndexName(),
                '_source' => ['has_draft_contents'],
            ]
        );

        $isDrafted = $result['found'] && isset($result['_source']['has_draft_contents'])
            ? $result['_source']['has_draft_contents']
            : false;

        if (false === $strictDraftMode && !$isDrafted) {
            $isDrafted = $this->contentMgr->hasGlobalContentDraft();
        }

        $pageTags = $this->getPageTag($page);

        return [
            'id' => $page->getUid(),
            'title' => $page->getTitle(),
            'type' => $type ? $type->uniqueName() : null,
            'type_data' => $type,
            'category' => $this->pageCategoryManager->getCategoryByPage($page),
            'is_online' => $page->isOnline(),
            'is_drafted' => $isDrafted,
            'url' => $page->getUrl(),
            'tags' => $pageTags ? array_map(
                static function (KeyWord $keyword) {
                    return [
                        'uid' => $keyword->getUid(),
                        'label' => $keyword->getKeyWord(),
                    ];
                },
                $pageTags->getTags()->toArray()
            ) : [],
            'seo' => $pageSeo,
            'lang' => $this->multiLangMgr->getLangByPage($page),
            'created_at' => $page->getCreated()->format('Y-m-d H:i:s'),
            'modified' => $page->getModified()->format('Y-m-d H:i:s'),
            'published_at' => $page->getPublishing()
                ? $page->getPublishing()->format('Y-m-d H:i:s')
                : null,
            'search_engine_activated' => $this->searchEngineManager->googleSearchEngineIsActivated(),
        ];
    }

    /**
     * Applies ::format() on every item of the provided collection.
     *
     * @param mixed $collection
     * @param bool  $strictDraftMode
     *
     * @return array
     * @see ::format()
     */
    public function formatCollection($collection, bool $strictDraftMode): array
    {
        $result = [];
        foreach ($collection as $page) {
            try {
                $result[] = $this->format($page, $strictDraftMode);
            } catch (Exception $exception) {
                $this->logger->error(
                    sprintf(
                        '%s : %s :%s',
                        __CLASS__,
                        __FUNCTION__,
                        $exception->getMessage()
                    )
                );
                continue;
            }
        }

        return $result;
    }

    /**
     * Returns an instance of Page if provided uid exists else null.
     *
     * @param string $uid The requested page uid
     *
     * @return Page|null
     */
    public function get(string $uid): ?Page
    {
        return $this->repository->find($uid);
    }

    /**
     * Creates and persist a new page according to provided data.
     *
     * @param array $data
     *
     * @return Page
     */
    public function create(array $data): Page
    {
        $data = $this->prepareDataWithLang($data);
        $uid = $data['uid'] ?? null;

        $page = new Page($uid);
        $page->setSite($this->getSite());
        $page->setLayout($this->getLayout());

        if ($data['url']) {
            $page->setUrl($data['url']);
        }

        try {
            $page->setParent($this->getRootPage());
            $page->setState($data['state'] ?? Page::STATE_OFFLINE);
            $page->setPosition($this->count() + 1);
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        unset($data['is_online'], $data['uid'], $data['state'], $data['url']);

        $this->entityMgr->persist($page);
        $data['seo'] = $data['seo'] ?? [];
        $data['type'] = $data['type'] ?? $this->typeMgr->getDefaultType()->uniqueName();

        $redirects = $data['redirections'] ?? null;
        unset($data['redirections']);

        $this->update($page, ['title' => $data['title'] ?? ''], false);
        unset($data['title']);
        $this->update($page, ['type' => $data['type']], false);
        unset($data['type']);

        $this->currentPage = $page;
        $this->update($page, $data);

        if ($redirects !== null) {
            $this->handleRedirections($page, (array)$redirects);
        }

        $this->elsMgr->indexPage($page);
        $this->currentPage = null;

        return $page;
    }

    /**
     * Update page.
     *
     * @param Page  $page    The page to update
     * @param array $data    The data to use to update the page
     * @param bool  $doFlush If true, PageManager will invoke EntityManager::flush()
     */
    public function update(Page $page, array $data, bool $doFlush = true): void
    {
        try {
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
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
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

        if ($this->multiLangMgr->isActive()) {
            $this->pageAssociationMgr->deleteAssociatedPage($page);
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
     * @throws ClassNotFoundException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws OptimisticLockException
     * @throws UnknownPropertyException
     * @throws \BackBee\Exception\InvalidArgumentException
     */
    public function duplicate(Page $page, array $data): Page
    {
        $this->entityMgr->beginTransaction();

        $data = $this->prepareDataWithLang($data);
        $data['title'] = $data['title'] ?? '';
        $data['tags'] = isset($data['tags']) ? (array)$data['tags'] : [];
        $putContentOnline = isset($data['put_content_online']) && $data['put_content_online'];
        $isOnline = $data['is_online'] ?? false;
        $copy = new Page($data['uid'] ?? null);
        unset($data['type'], $data['is_online'], $data['put_content_online'], $data['uid']);

        $copy->setSite($this->getSite());
        $copy->setLayout($this->getLayout());
        $copy->setParent($this->getRootPage());
        $copy->setState(true === $isOnline ? Page::STATE_ONLINE : Page::STATE_OFFLINE);
        $copy->setPosition($this->count() + 1);

        $this->entityMgr->persist($copy);
        $this->typeMgr->associate($copy, $this->typeMgr->findByPage($page));

        $this->update($copy, ['title' => $data['title']], false);
        unset($data['title']);

        $this->currentPage = $copy;

        $seoPage = $this->seoMetadataManager->getPageSeoMetadata($page);

        $data['seo'] = [
            'title' => $seoPage['title'] ?? $data['title'],
            'description' => $seoPage['description'] ?? '',
        ];

        $this->update($copy, $data, false);

        $this->entityMgr->flush();

        $copy->setContentset(
            $this->contentMgr->duplicateContent(
                $page->getContentSet(),
                $this->bbToken,
                null,
                $putContentOnline
            )
        );

        $this->entityMgr->flush();
        $this->entityMgr->commit();

        $this->elsMgr->indexPage($copy);
        $this->currentPage = null;

        return $copy;
    }

    /**
     * @param Page   $page  The page to update
     * @param string $value The value to set
     *
     * @throws InvalidArgumentException if provided value is not string or
     *                                   does not contain at least 2 characters
     */
    protected function runSetTitle(Page $page, $value): void
    {
        if (!is_string($value) || 2 > strlen($value)) {
            throw new InvalidArgumentException(
                '`title` must be type of string and contain at least 2 characters'
            );
        }

        $page->setTitle($value);
    }

    /**
     * @param Page   $page  The page to update
     * @param string $value The value to set
     *
     * @throws {@see ::genericRunSetDateTime}
     */
    protected function runSetPublishedAt(Page $page, $value): void
    {
        $this->genericRunSetDateTime($page, 'setPublishing', $value);
    }

    /**
     * @param Page   $page  The page to update
     * @param string $value The value to set
     *
     * @throws {@see ::genericRunSetDateTime}
     */
    protected function runSetCreatedAt(Page $page, $value): void
    {
        $this->genericRunSetDateTime($page, 'setCreated', $value);
    }

    /**
     * @param Page   $page  The page to update
     * @param string $value The value to set
     *
     * @throws {@see ::genericRunSetDateTime}
     */
    protected function runSetModifiedAt(Page $page, $value): void
    {
        $this->genericRunSetDateTime($page, 'setModified', $value);
    }

    /**
     * Generic method to set datetime for page.
     *
     * @param Page   $page   the page to update
     * @param string $method the method to call
     * @param string $value
     *
     * @throws InvalidArgumentException if the provided value is not a valid datetime string
     */
    protected function genericRunSetDateTime(Page $page, $method, $value): void
    {
        try {
            $datetime = new DateTime($value);
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
            throw new InvalidArgumentException(
                sprintf(
                    '[%s - %s] Failed to create an instance of DateTime, "%s" is not valid.',
                    __METHOD__,
                    $method,
                    $value
                )
            );
        }

        $page->$method($datetime);
    }

    /**
     * @param Page $page  The page to update
     * @param bool $value The value to set
     *
     * @throws Exception
     */
    protected function runSetIsOnline(Page $page, bool $value): void
    {
        $today = new DateTime();
        try {
            $page->setState($value ? Page::STATE_ONLINE : Page::STATE_OFFLINE);
            if (true === $value && !$page->isRoot()) {
                $page->setPublishing($today);
            }
            if (false === $value && !$page->isRoot() && $page->getPublishing() <= $today) {
                $page->setPublishing();
            }
        } catch (LogicException $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
            throw new LogicException('Home page cannot be offline');
        }
    }

    /**
     * @param Page  $page
     * @param mixed $values
     */
    protected function runSetTags(Page $page, $values): void
    {
        $pageTag = $this->getPageTag($page);

        if (null === $pageTag) {
            $pageTag = new PageTag($page);
            $this->entityMgr->persist($pageTag);
        }

        $pageTag->resetTags();
        foreach ((array)$values as $tagData) {
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
     * Run set SEO.
     *
     * @param Page  $page
     * @param array $data
     */
    protected function runSetSeo(Page $page, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $bag = $page->getMetaData() ?: new MetaDataBag();

        $data = array_merge(
            [
                'title' => $bag->get('title')
                    ? $bag->get('title')->getAttribute('content')
                    : '',
                'description' => $bag->get('description')
                    ? $bag->get('description')->getAttribute('content')
                    : '',
                'keywords' => $bag->get('keywords')
                    ? $bag->get('keywords')->getAttribute('content')
                    : '',
                'index' => !$bag->get('index') || $bag->get('index')->getAttribute('content'),
                'follow' => !$bag->get('follow') || $bag->get('follow')->getAttribute('content'),
            ],
            $data
        );

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
    protected function runSetType(Page $page, $typeName): void
    {
        $type = $this->typeMgr->find($typeName);
        if ($type === null) {
            throw new InvalidArgumentException("You selected `$typeName` as page type but it does not exist.");
        }

        $this->typeMgr->associate($page, $type);
        if ($this->hydratePage && $this->entityMgr->getUnitOfWork()->isScheduledForInsert($page)) {
            $this->typeMgr->hydratePageContentsByType($type, $page, $this->bbToken);
        }
    }

    /**
     * @param Page $page The page to update
     */
    protected function runSetLang(Page $page): void
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
    protected function runSetUrl(Page $page, $value): void
    {
        $page->setUrl($value);
    }

    /**
     * @param Page $page
     * @param      $value
     */
    protected function runSetCategory(Page $page, $value): void
    {
        if (false === $value) {
            return;
        }

        $this->pageCategoryManager->associatePageAndCategory($page, $value);
    }

    /**
     * Create redirection for all URLs in $redirects to provided page's url.
     *
     * @param Page  $page
     * @param array $redirections
     */
    protected function handleRedirections(Page $page, array $redirections = []): void
    {
        try {
            foreach ($redirections as $redirection) {
                $redirection = '/' . preg_replace('~^/~', '', $redirection);
                $pageRedirection = new PageRedirection($redirection, $page->getUrl());
                $this->entityMgr->persist($pageRedirection);
            }

            $this->entityMgr->flush();
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Returns PageTag entity for the given page. If it does not exist, this
     * method will create and persist it into database.
     *
     * @param Page $page
     *
     * @return null|object
     */
    public function getPageTag(Page $page)
    {
        return $this->entityMgr->getRepository(PageTag::class)->findOneBy(
            [
                'page' => $page,
            ]
        );
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
            $root = $this->entityMgr->getRepository(Page::class)->findOneBy(
                [
                    '_url' => '/',
                ]
            );
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
     * Prepare data with lang.
     *
     * @param array $data
     *
     * @return array
     */
    protected function prepareDataWithLang(array $data): array
    {
        try {
            if (isset($data['lang'])) {
                $lang = $data['lang'];
                unset($data['lang']);
                $data = ['lang' => $lang] + $data;

                $langEntity = $this->entityMgr->find(Lang::class, $lang);
                if (null === $langEntity || !$langEntity->isActive()) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Lang "%s" does not exist or is not activated',
                            $lang
                        )
                    );
                }
            } elseif (null !== $defaultLang = $this->multiLangMgr->getDefaultLang()) {
                $data['lang'] = $defaultLang['id'];
            }

            if (isset($data['lang'])) {
                $this->currentLang = $this->entityMgr->find(Lang::class, $data['lang']);
            }
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $data;
    }
}
