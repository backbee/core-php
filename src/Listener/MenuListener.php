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

namespace BackBeeCloud\Listener;

use BackBee\BBApplication;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Page\PageContentManager;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Site\Site;
use BackBeeCloud\Entity\Lang;
use Doctrine\ORM\EntityManager;
use Exception;

/**
 * Class MenuListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author  Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class MenuListener
{
    /**
     * @var BBApplication
     */
    private static $bbApp;

    /**
     * @var EntityManager
     */
    private static $entityManager;

    /**
     * @var PageContentManager
     */
    private static $pageContentManager;

    /**
     * MenuListener constructor.
     *
     * @param BBApplication      $bbApp
     * @param PageContentManager $pageContentManager
     */
    public function __construct(BBApplication $bbApp, PageContentManager $pageContentManager)
    {
        self::$bbApp = $bbApp;
        self::$entityManager = $bbApp->getEntityManager();
        self::$pageContentManager = $pageContentManager;
    }

    /**
     * Occurs on `basic.menu.persist`
     *
     * @param Event $event
     */
    public static function onPrePersist(Event $event): void
    {
        $menu = $event->getTarget();
        $param = $menu->getParam('items');

        if (empty($param['value'])) {
            $homepage = self::$entityManager->getRepository(Page::class)->getRoot(
                self::$entityManager->getRepository(Site::class)->findOneBy([])
            );
            if ($homepage) {
                $menu->setParam(
                    'items',
                    [
                        [
                            'id' => $homepage->getUid(),
                            'url' => $homepage->getUrl(),
                            'label' => $homepage->getTitle(),
                        ],
                    ]
                );
            }
        }
    }

    /**
     * Called on `basic.menu.render` event.
     *
     * @param RendererEvent $event
     */
    public static function onRender(RendererEvent $event): void
    {
        $renderer = $event->getRenderer();
        $block = $event->getTarget();
        $items = $block->getParamValue('items');
        $validItems = [];

        try {
            foreach ($items as $item) {
                if (false !== $item['id'] && null !== ($page = self::getPageByUid($item['id']))) {
                    $firstHeading = self::$pageContentManager->getFirstHeadingFromPage($page) ?: $page->getTitle();
                    $label = $block->getParamValue('title_to_be_displayed') === 'first_heading' ?
                        $firstHeading : $page->getTitle();

                    $item['url'] = $page->getUrl();
                    $item['label'] = $label;
                    $item['is_online'] = $page->isOnline();
                    $item['is_current'] = $renderer->getCurrentPage() === $page;

                    $validChildren = [];
                    if (isset($item['children'])) {
                        foreach ((array)$item['children'] as $child) {
                            if (null !== ($page = self::getPageByUid($child['id'], self::$bbApp))) {
                                $child['url'] = $page->getUrl();
                                $child['label'] = $page->getTitle();
                                $child['is_online'] = $page->isOnline();
                                $child['is_current'] = $renderer->getCurrentPage() === $page;
                                $validChildren[] = $child;
                            }
                        }
                    }

                    $item['children'] = $validChildren;
                    $validItems[] = $item;
                }
            }

            if (null !== self::$bbApp->getBBUserToken()) {
                $block->setParam('items', $validItems);
                self::$entityManager->flush($block->getDraft() ?: $block);
            }

            $renderer->assign('items', $validItems);
        } catch (Exception $exception) {
            self::$bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }
    }

    /**
     * Get page by uid.
     *
     * @param string|null $uid
     *
     * @return Page|null
     */
    private static function getPageByUid(?string $uid = null): ?Page
    {
        $page = null;
        $multiLangMgr = self::$bbApp->getContainer()->get('multilang_manager');

        try {
            if (null !== ($page = self::$entityManager->find(Page::class, $uid))) {
                if (null === self::$bbApp->getBBUserToken() && !$page->isOnline()) {
                    return null;
                }

                if ($page->isRoot() &&
                    null !== ($currentLang = $multiLangMgr->getCurrentLang()) &&
                    null !== ($lang = self::$entityManager->find(Lang::class, $currentLang))
                ) {
                    $page = $multiLangMgr->getRootByLang($lang);
                }
            }
        } catch (Exception $exception) {
            self::$bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $page;
    }
}
