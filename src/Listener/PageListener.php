<?php

namespace BackBeeCloud\Listener;

use BackBee\ClassContent\Basic\Menu;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Event\Event;
use BackBee\Exception\BBException;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Site\Site;
use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\Entity\PageRedirection;
use BackBeeCloud\Entity\PageTag;
use BackBeeCloud\Entity\PageType;
use BackBeeCloud\PageType\ArticleType;
use BackBeeCloud\PageType\HomeType;
use BackBeeCloud\PageType\SearchResultType;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageListener
{
    /**
     * @var array
     */
    protected static $redirections = [];

    /**
     * @var array
     */
    protected static $toDelete = [];

    /**
     * Listens to "bbapplication.init" to ensures that home page is associated to
     * "home" page type. It runs its process only if the application is not restored.
     *
     * @param Event $event
     *
     * @throws DBALException
     * @throws OptimisticLockException
     */
    public static function onApplicationInit(Event $event): void
    {
        $app = $event->getApplication();
        if ($app->isRestored()) {
            return;
        }

        $pages = [];

        try {
            $entityManager = $app->getEntityManager();
            $site = $entityManager->getRepository(Site::class)->findOneBy([]);
            $rootPage = $app->getEntityManager()->getRepository(Page::class)->getRoot($site);
            if (null !== $rootPage) {
                $pages[] = $rootPage;
            }

            $multilangManager = $app->getContainer()->get('multilang_manager');
            if (null !== $multilangManager->getDefaultLang()) {
                foreach ($multilangManager->getAllLangs() as $lang) {
                    if (null !== $page = $multilangManager->getRootByLang(new Lang($lang['id']))) {
                        $pages[] = $page;
                    }
                }
            }

            $homePageType = new HomeType();
            $pageTypeManager = $app->getContainer()->get('cloud.page_type.manager');
            foreach ($pages as $page) {
                if (null !== $pageTypeManager->getAssociation($page)) {
                    continue;
                }

                $association = $pageTypeManager->associate($page, $homePageType);
                $entityManager->flush($association);
            }
        } catch (DBALException $exception) {
            if (1 === preg_match('/42S02/i', $exception->getMessage())) {
                // database is not installed yet, end of process
                return;
            }

            throw $exception;
        }
    }

    public static function handleUriCollisionOnFlushPage(Event $event): void
    {
        $page = $event->getTarget();
        $app = $event->getApplication();
        $entyMgr = $app->getEntityManager();
        $uow = $entyMgr->getUnitOfWork();

        if ($uow->isScheduledForDelete($page)) {
            return;
        }

        $container = $app->getContainer();
        if ($container->get('cloud.page_type.manager')->findByPage($page) instanceof SearchResultType) {
            return;
        }

        if (1 === preg_match('~/search$~', $page->getUrl())) {
            $elsMgr = $container->get('elasticsearch.manager');
            $count = $elsMgr->getClient()->count(
                [
                    'index' => $elsMgr->getIndexName(),
                    'body' => [
                        'query' => [
                            'regexp' => [
                                'url' => [
                                    'value' => $page->getUrl() . '\-[0-9]+',
                                ],
                            ],
                        ],
                    ],
                ]
            )['count'];

            $url = $page->getUrl() . '-' . ++$count;
            while ($url !== $container->get('rewriting.urlgenerator')->getUniqueness($page, $url)) {
                $url = $page->getUrl() . '-' . ++$count;
            }

            $page->setUrl($url);
            $uow->recomputeSingleEntityChangeSet($entyMgr->getClassMetadata(Page::class), $page);
        }
    }

    public static function onFlush(Event $event): void
    {
        $page = $event->getTarget();
        if ($page->isRoot()) {
            return;
        }

        $app = $event->getApplication();
        $entyMgr = $app->getEntityManager();
        $uow = $entyMgr->getUnitOfWork();
        if ($uow->isScheduledForDelete($page)) {
            self::$toDelete[] = $page->getUrl();

            return;
        }

        if ($uow->isScheduledForInsert($page)) {
            $entyMgr
                ->getRepository(PageRedirection::class)
                ->createQueryBuilder('pr')
                ->delete()
                ->where('pr.toRedirect = :to_redirect')
                ->setParameter('to_redirect', $page->getUrl())
                ->getQuery()
                ->getResult();

            return;
        }

        $container = $app->getContainer();
        $container->get('elasticsearch.manager')->indexPage($page);
        if ($container->get('cloud.page_type.manager')->findByPage($page) instanceof HomeType) {
            $changes = $uow->getEntityChangeSet($page);
            if (isset($changes['_url'])) {
                $page->setUrl($changes['_url'][0]);
                $uow->recomputeSingleEntityChangeSet($entyMgr->getClassMetadata(Page::class), $page);
                $container->get('elasticsearch.manager')->indexPage($page);
            }

            return;
        }

        $changes = $uow->getEntityChangeSet($page);
        if (!isset($changes['_title'])) {
            return;
        }

        $currUrl = $page->getUrl();
        if (isset($changes['_url']) && false !== $changes['_url'][0]) {
            $currUrl = $changes['_url'][0];
        }

        $newUrl = $container->get('rewriting.urlgenerator')->generate($page, null, true);
        if ($newUrl === $currUrl) {
            return;
        }

        self::$redirections[$currUrl] = $newUrl;
        $page->setUrl($newUrl);
        $uow->recomputeSingleEntityChangeSet($entyMgr->getClassMetadata(Page::class), $page);
    }

    /**
     * Occurs on "nestednode.page.postremove" to delete associated PageType, PageTag
     * and PageLang.
     *
     * @param Event $event
     *
     * @throws OptimisticLockException
     */
    public static function onPostRemove(Event $event): void
    {
        $page = $event->getTarget();
        $entityMgr = $event->getApplication()->getEntityManager();

        if ($entityMgr instanceof EntityManager) {
            // Delete associated PageTag
            if (null !== $pagetag = $entityMgr->getRepository(PageTag::class)->findOneBy(['page' => $page])) {
                $entityMgr->remove($pagetag);
            }

            // Delete associated PageType
            if (null !== $pagetype = $entityMgr->getRepository(PageType::class)->findOneBy(['page' => $page])) {
                $entityMgr->remove($pagetype);
            }

            // Delete associated PageLang
            if (null !== $pagelang = $entityMgr->getRepository(PageLang::class)->findOneBy(['page' => $page])) {
                $entityMgr->remove($pagelang);
            }

            $entityMgr->flush();
        }
    }

    /**
     * Occurs on:
     *     - rest.controller.classcontentcontroller.postaction.precall
     *     - rest.controller.classcontentcontroller.putaction.precall
     *     - backbeecloud.api.controller.contentcontroller.delete.precall
     *
     * @param Event $event
     *
     * @throws BBException
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     */
    public static function onRestContentUpdatePostcall(Event $event): void
    {
        $app = $event->getApplication();
        $querybag = $app->getRequest()->query;
        if ($querybag->has('page_uid')) {
            $page = $app->getEntityManager()->find(
                Page::class,
                $querybag->get('page_uid')
            );

            if ($page) {
                $app->getContainer()->get('elasticsearch.manager')->indexPage($page);
            }
        }
    }

    /**
     * On page post change.
     *
     * @param Event $event
     *
     * @throws OptimisticLockException
     */
    public static function onPagePostChange(Event $event): void
    {
        $entyMgr = $event->getApplication()->getEntityManager();
        foreach (self::$toDelete as $url) {
            $entyMgr
                ->getRepository(PageRedirection::class)
                ->createQueryBuilder('pr')
                ->delete()
                ->where('pr.toRedirect = :to_redirect')
                ->setParameter('to_redirect', $url)
                ->orWhere('pr.target = :target')
                ->setParameter('target', $url)
                ->getQuery()
                ->getResult();
        }

        self::$toDelete = [];
        foreach (self::$redirections as $currUrl => $newUrl) {
            $entyMgr->getRepository(PageRedirection::class)->createQueryBuilder('pr')
                ->update()
                ->set('pr.target', ':new_target')
                ->setParameter('new_target', $newUrl)
                ->where('pr.target = :old_target')
                ->setParameter('old_target', $currUrl)
                ->getQuery()
                ->getResult();

            $pageRedirection = new PageRedirection($currUrl, $newUrl);
            $entyMgr->persist($pageRedirection);
        }

        $entyMgr->flush();
        self::$redirections = [];
    }

    /**
     * Occurs on `nestednode.page.render` to set the right layout name to use.
     *
     * @param RendererEvent $event
     *
     * @throws BBException
     */
    public static function onPageRender(RendererEvent $event): void
    {
        $page = $event->getTarget();
        $app = $event->getApplication();
        $typeMgr = $app->getContainer()->get('cloud.page_type.manager');

        if (null === $type = $typeMgr->findByPage($page)) {
            $type = $typeMgr->getDefaultType();
        }

        $renderer = $event->getRenderer();
        $renderer->assign('layout_name', $type->layoutName());

        $userPreferenceManager = $app->getContainer()->get('user_preference.manager');
        if ($gaData = $userPreferenceManager->dataOf('google-analytics')) {
            $code = isset($gaData['code']) ? (string)$gaData['code'] : '';
            if (1 === preg_match('#^UA\-[0-9]+\-[0-9]+$#', $code)) {
                $renderer->assign('google_analytics_code', $code);
            }
        }

        if ($gtmData = $userPreferenceManager->dataOf('gtm-analytics')) {
            $code = isset($gtmData['code']) ? (string)$gtmData['code'] : '';
            if (1 === preg_match('#^GTM\-[a-zA-Z0-9]+$#', $code)) {
                $renderer->assign('gtm_code', $code);
            }
        }

        if ($faData = $userPreferenceManager->dataOf('facebook-analytics')) {
            $code = isset($faData['code']) ? (string)$faData['code'] : '';
            if (1 === preg_match('#^[0-9]{15}$#', $code)) {
                $renderer->assign('facebook_analytics_code', $code);
            }
        }

        if ($app->getAppParameter('privacy_policy') && $data = $userPreferenceManager->dataOf('privacy-policy')) {
            $multilangManager = $app->getContainer()->get('multilang_manager');
            if ($multilangManager->isActive() && $currentLang = $multilangManager->getCurrentLang()) {
                $currentLang = $multilangManager->getCurrentLang();
                foreach ($data as $key => $value) {
                    $prefix = $currentLang . '_';
                    if (1 === preg_match(sprintf('~^%s~', $prefix), $key)) {
                        $renderer->assign(str_replace($prefix, '', $key), $value);
                    }
                }

                return;
            }

            $renderer->assign(
                'banner_message',
                $data['banner_message'] ?? null
            );
            $renderer->assign(
                'learn_more_url',
                $data['learn_more_url'] ?? null
            );
            $renderer->assign(
                'learn_more_link_title',
                $data['learn_more_link_title'] ?? null
            );
        }
    }

    /**
     * Called on "nestednode.page.postrender" event.
     *
     * @param RendererEvent $event
     */
    public static function onPostRender(RendererEvent $event): void
    {
        if ($event->getApplication()->getBBUserToken() === null) {
            return;
        }

        $renderer = $event->getRenderer();
        $renderer->setRender(
            str_replace(
                '</body>',
                $renderer->partial('common/hook_form.js.twig') .
                $renderer->partial('common/hook_session.js.twig') .
                $renderer->partial('Optimizeimage/hook.js.twig') . '</body>',
                $renderer->getRender()
            )
        );
    }

    /**
     * Occurs on `rest.controller.pagecontroller.deleteaction.postcall` to hard
     * delete the page.
     *
     * @param Event $event
     *
     * @throws BBException
     * @throws OptimisticLockException
     */
    public static function onPageDeletePostcall(Event $event): void
    {
        $app = $event->getApplication();
        $pageAssociationMgr = $app->getContainer()->get('cloud.multilang.page_association.manager');
        $page = $app->getRequest()->attributes->get('page');
        if (!($page instanceof Page)) {
            return;
        }

        $entityMgr = $app->getEntityManager();
        $bbtoken = $app->getBBUserToken();

        if ($entityMgr instanceof EntityManager) {
            foreach ($entityMgr->getRepository(Menu::class)->findAll() as $menu) {
                $originalDraft = $menu->getDraft();
                $menu->setDraft(null);
                self::cleanMenu($menu, $page);
                $draft = $originalDraft;
                if (null === $draft && null !== $bbtoken) {
                    $draft = $entityMgr->getRepository(Revision::class)->getDraft($menu, $bbtoken);
                }

                if (null !== $draft) {
                    $menu->setDraft($draft);
                    self::cleanMenu($menu, $page);
                }

                $menu->setDraft($originalDraft);
            }

            if ($app->getContainer()->get('multilang_manager')->isActive()) {
                $pageAssociationMgr->deleteAssociatedPage($page);
            }

            $app->getContainer()->get('cloud.page_category.manager')->deleteAssociationByPage($page);

            $entityMgr->getRepository(Page::class)->deletePage($page);
            $entityMgr->flush();
        }
    }

    /**
     * On page not found exception.
     *
     * @param GetResponseForExceptionEvent $event
     */
    public static function onPageNotFoundException(GetResponseForExceptionEvent $event): void
    {
        $exception = $event->getException();
        if (
            !($exception instanceof FrontControllerException)
            || Response::HTTP_NOT_FOUND !== $exception->getStatusCode()
        ) {
            return;
        }

        $app = $event->getKernel()->getApplication();
        $entyMgr = $app->getEntityManager();
        $pageRedirection = $entyMgr->getRepository(PageRedirection::class)->findOneBy(
            [
                'toRedirect' => $app->getRequest()->getPathInfo(),
            ]
        );
        if ($pageRedirection) {
            $event->setResponse(
                new RedirectResponse(
                    str_replace(
                        $app->getRequest()->getPathInfo(),
                        $pageRedirection->target(),
                        $app->getRequest()->getRequestUri()
                    ),
                    Response::HTTP_MOVED_PERMANENTLY
                )
            );
        }
    }

    /**
     * On Rss action pre call.
     *
     * @param PreRequestEvent $event
     *
     * @throws FrontControllerException
     */
    public static function onRssActionPreCall(PreRequestEvent $event): void
    {
        $app = $event->getApplication();
        $request = $event->getTarget();

        $uri = '/' . $request->attributes->get('uri');
        $page = $app->getEntityManager()->getRepository(Page::class)->findOneBy(
            [
                '_url' => $uri,
            ]
        );

        if (null === $page) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }

        $pagetype = $app->getContainer()->get('cloud.page_type.manager')->findByPage($page);
        if ($pagetype instanceof ArticleType) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }

        $autoblocks = $app->getEntityManager()->getRepository(ContentAutoblock::class)->findBy(
            [
                '_uid' => $app->getContainer()->get('cloud.content_manager')->getUidsFromPage($page),
            ]
        );
        if (false === $autoblocks) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }
    }

    /**
     * Removes the provided page from menu's items.
     *
     * @param Menu $menu
     * @param Page $pageToRemove
     *
     * @return void
     */
    protected static function cleanMenu(Menu $menu, Page $pageToRemove): void
    {
        $items = $menu->getParamValue('items');
        $validItems = [];
        foreach ($items as $item) {
            if (false !== $item['id'] && $pageToRemove->getUid() === $item['id']) {
                continue;
            }

            $validItems[] = $item;
        }

        $menu->setParam('items', $validItems);
    }
}
