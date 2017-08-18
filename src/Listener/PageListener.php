<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Entity\PageRedirection;
use BackBeeCloud\Entity\PageTag;
use BackBeeCloud\Entity\PageType;
use BackBeeCloud\PageType\ArticleType;
use BackBeeCloud\PageType\HomeType;
use BackBeeCloud\PageType\SearchResultType;
use BackBee\ClassContent\Basic\Menu;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
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

    public static function handleUriCollisionOnFlushPage(Event $event)
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
            $count = $elsMgr->getClient()->count([
                'index' => $elsMgr->getIndexName(),
                'type'  => $elsMgr->getPageTypeName(),
                'body'  => [
                    'query' => [
                        'regexp' => [
                            'url' => [
                                'value' => $page->getUrl() . '\-[0-9]+',
                            ],
                        ],
                    ],
                ],
            ])['count'];

            $url = $page->getUrl() . '-' . ++$count;
            while ($url !== $container->get('rewriting.urlgenerator')->getUniqueness($page, $url)) {
                $url = $page->getUrl() . '-' . ++$count;
            }

            $page->setUrl($url);
            $uow->recomputeSingleEntityChangeSet($entyMgr->getClassMetadata(Page::class), $page);
        }
    }

    public static function onFlush(Event $event)
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
                ->getResult()
            ;

            return;
        }

        $container = $app->getContainer();
        $container->get('elasticsearch.manager')->indexPage($page);
        if ($container->get('cloud.page_type.manager')->findByPage($page) instanceof HomeType) {
            return;
        }

        $changes = $uow->getEntityChangeSet($page);
        if (!isset($changes['_title'])) {
            return;
        }

        $currUrl = $page->getUrl();
        if (isset($changes['_url']) && false != $changes['_url'][0]) {
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
     * Occurs on:
     *     - rest.controller.classcontentcontroller.postaction.precall
     *     - rest.controller.classcontentcontroller.putaction.precall
     *     - backbeecloud.api.controller.contentcontroller.delete.precall
     *
     * @param  Event  $event
     */
    public static function onRestContentUpdatePostcall(Event $event)
    {
        $app = $event->getApplication();
        $querybag = $app->getRequest()->query;
        if ($querybag->has('page_uid')) {
            $page = $app
                ->getEntityManager()
                ->find('BackBee\NestedNode\Page', $querybag->get('page_uid'))
            ;

            $app->getContainer()->get('elasticsearch.manager')->indexPage($page);
        }
    }

    public static function onPagePostChange(Event $event)
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
                ->getResult()
            ;
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
                ->getResult()
            ;

            $pageRedirection = new PageRedirection($currUrl, $newUrl);
            $entyMgr->persist($pageRedirection);
        }

        $entyMgr->flush();
        self::$redirections = [];
    }

    /**
     * Occurs on `nestednode.page.render` to set the right layout name to use.
     *
     * @param  RendererEvent $event
     */
    public static function onPageRender(RendererEvent $event)
    {
        $page = $event->getTarget();
        $app = $event->getApplication();
        $typeMgr = $app->getContainer()->get('cloud.page_type.manager');

        if (null === $type = $typeMgr->findByPage($page)) {
            $type = $typeMgr->getDefaultType();
        }

        $event->getRenderer()->assign('layout_name', $type->layoutName());
        if ($faviconData = $app->getContainer()->get('user_preference.manager')->dataOf('favicon')) {
            $event->getRenderer()->assign('favicon_data', array_map(function ($url) {
                return str_replace(['http:', 'https:'], '', $url);
            }, $faviconData));
        }

        if ($gaData = $app->getContainer()->get('user_preference.manager')->dataOf('google-analytics')) {
            $code = isset($gaData['code']) ? (string) $gaData['code'] : '';
            if (1 === preg_match('#^UA\-[0-9]+\-[0-9]+$#', $code)) {
                $event->getRenderer()->assign('google_analytics_code', $code);
            }
        }

        if ($faData = $app->getContainer()->get('user_preference.manager')->dataOf('facebook-analytics')) {
            $code = isset($faData['code']) ? (string) $faData['code'] : '';
            if (1 === preg_match('#^[0-9]{15}$#', $code)) {
                $event->getRenderer()->assign('facebook_analytics_code', $code);
            }
        }
    }

    /**
     * Occurs on `rest.controller.pagecontroller.deleteaction.postcall` to hard
     * delete the page.
     *
     * @param  Event  $event
     */
    public static function onPageDeletePostcall(Event $event)
    {
        $app = $event->getApplication();
        $page = $app->getRequest()->attributes->get('page');
        if (!($page instanceof Page)) {
            return;
        }

        $entyMgr = $app->getEntityManager();
        $pagetype = $entyMgr->getRepository(PageType::class)->findOneBy(['page' => $page]);
        if (null !== $pagetype) {
            $entyMgr->remove($pagetype);
        }

        $pageTag = $entyMgr->getRepository(PageTag::class)->findOneBy(['page' => $page]);
        if (null !== $pageTag) {
            $entyMgr->remove($pageTag);
        }

        $bbtoken = $app->getBBUserToken();
        foreach ($entyMgr->getRepository(Menu::class)->findAll() as $menu) {
            $originalDraft = $menu->getDraft();
            $menu->setDraft(null);
            self::cleanMenu($menu, $page);
            $draft = $originalDraft;
            if (null === $draft && null !== $bbtoken) {
                $draft = $entyMgr->getRepository(Revision::class)->getDraft($menu, $bbtoken, false);
            }

            if (null !== $draft) {
                $menu->setDraft($draft);
                self::cleanMenu($menu, $page);
            }

            $menu->setDraft($originalDraft);
        }

        $entyMgr->getRepository(Page::class)->deletePage($page);
        $entyMgr->flush();
    }

    public static function onPageNotFoundException(GetResponseForExceptionEvent $event)
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
        $pageRedirection = $entyMgr->getRepository(PageRedirection::class)->findOneBy([
            'toRedirect' => $app->getRequest()->getPathInfo(),
        ]);
        if ($pageRedirection) {
            $event->setResponse(new RedirectResponse(str_replace(
                $app->getRequest()->getPathInfo(),
                $pageRedirection->target(),
                $app->getRequest()->getRequestUri()
            )), Response::HTTP_MOVED_PERMANENTLY);
        }
    }

    public static function onRssActionPreCall(PreRequestEvent $event)
    {
        $app = $event->getApplication();
        $request = $event->getTarget();

        $uri = '/' . $request->attributes->get('uri');
        $page = $app->getEntityManager()->getRepository(Page::class)->findOneBy([
            '_url' => $uri,
        ]);

        if (null === $page) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }

        $pagetype = $app->getContainer()->get('cloud.page_type.manager')->findByPage($page);
        if ($pagetype instanceof ArticleType) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }

        $autoblocks = $app->getEntityManager()->getRepository(ContentAutoblock::class)->findBy([
            '_uid' => $app->getContainer()->get('cloud.content_manager')->getUidsFromPage($page),
        ]);
        if (false == $autoblocks) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }
    }

    /**
     * Removes the provided page from menu's items.
     *
     * @param  Menu   $menu
     * @param  Page   $pageToRemove
     * @return
     */
    protected static function cleanMenu(Menu $menu, Page $pageToRemove)
    {
        $items = $menu->getParamValue('items');
        $validItems = [];
        foreach ($items as $item) {
            if (false != $item['id'] && $pageToRemove->getUid() === $item['id']) {
                continue;
            }

            $validItems[] = $item;
        }

        $menu->setParam('items', $validItems);
    }
}
