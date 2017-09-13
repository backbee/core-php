<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Entity\Lang;
use BackBee\BBApplication;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Site\Site;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class MenuListener
{
    /**
     * Occurs on `basic.menu.persist`
     *
     * @param  Event  $event
     */
    public static function onPrePersist(Event $event)
    {
        $menu = $event->getTarget();
        $app = $event->getApplication();

        $param = $menu->getParam('items');
        if (false == $param['value']) {
            $homepage = $app
                ->getEntityManager()
                ->getRepository(Page::class)
                ->getRoot($app->getEntityManager()->getRepository(Site::class)->findOneBy([]))
            ;

            $menu->setParam('items', [
                [
                    'id'    => $homepage->getUid(),
                    'url'   => $homepage->getUrl(),
                    'label' => $homepage->getTitle(),
                ],
            ]);
        }
    }

    /**
     * Called on `basic.menu.render` event.
     *
     * @param  RendererEvent  $event
     */
    public static function onRender(RendererEvent $event)
    {
        $app = $event->getApplication();
        $entyMgr = $app->getEntityManager();
        $bbtoken = $app->getBBUserToken();
        $renderer = $event->getRenderer();

        $block = $event->getTarget();
        $items = $block->getParamValue('items');

        $validItems = [];
        foreach ($items as $item) {
            if (
                false != $item['id']
                && null !== $page = self::getPageByUid($item['id'], $app)
            ) {
                $item['url'] = $page->getUrl();
                $item['label'] = $page->getTitle();
                $item['is_online'] = $page->isOnline();
                $item['is_current'] = $renderer->getCurrentPage() === $page;

                $validChildren = [];
                foreach ((array) $item['children'] as $child) {
                    if (
                        false != $item['id']
                        && null !== $page = self::getPageByUid($child['id'], $app)
                    ) {
                        $child['url'] = $page->getUrl();
                        $child['label'] = $page->getTitle();
                        $child['is_online'] = $page->isOnline();
                        $child['is_current'] = $renderer->getCurrentPage() === $page;
                        $validChildren[] = $child;
                    }
                }

                $item['children'] = $validChildren;
                $validItems[] = $item;
            }
        }

        if (null !== $bbtoken) {
            $block->setParam('items', $validItems);
            $entyMgr->flush($block->getDraft() ?: $block);
        }

        $renderer->assign('items', $validItems);
    }

    protected static function getPageByUid($uid = null, BBApplication $app)
    {
        $bbtoken = $app->getBBUserToken();
        $entyMgr = $app->getEntityManager();
        $multilangMgr = $app->getContainer()->get('multilang_manager');
        if (null !== $page = $entyMgr->find(Page::class, (string) $uid)) {
            if (null === $bbtoken && !$page->isOnline()) {
                return null;
            }

            if (
                $page->isRoot()
                && null !== $currentLang = $multilangMgr->getCurrentLang()
            ) {
                $lang = $entyMgr->find(Lang::class, $currentLang);
                $page = $multilangMgr->getRootByLang($lang);
            }
        }

        return $page;
    }
}
