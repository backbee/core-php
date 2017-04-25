<?php

namespace BackBeeCloud\Listener;

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
            if (false != $item['id']) {
                if (null !== $page = $entyMgr->find('BackBee\NestedNode\Page', $item['id'])) {
                    if (null === $bbtoken && !$page->isOnline()) {
                        continue;
                    }

                    $item['url'] = $page->getUrl();
                    $item['label'] = $page->getTitle();
                    $item['is_online'] = $page->isOnline();
                    $item['is_current'] = $renderer->getCurrentPage() === $page;
                    $validItems[] = $item;
                }
            }
        }

        if (null !== $bbtoken) {
            $block->setParam('items', $validItems);
            $entyMgr->flush($block->getDraft() ?: $block);
        }

        $renderer->assign('items', $validItems);
    }
}
