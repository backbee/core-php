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

namespace BackBee\Renderer\Helper;

use BackBee\NestedNode\Page;
use BackBee\Renderer\Helper\AbstractHelper;

/**
 * Helper computing a priority for pages in sitemaps.
 *
 * @copyright    ©2017 - Lp digital
 * @author       Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class CloudLocationPriority extends AbstractHelper
{

    /**
     * @var array
     */
    private $menuItems;

    /**
     * Return a computed priority for a page:
     *  * for Home, return 1.0
     *  * for page placed in the header menu, return 0.8
     *  * for others return 0.5
     *
     * @param Page $page
     * @return string
     */
    public function __invoke(Page $page = null)
    {
        if ('/' === $page->getUrl()) {
            return '1.0';
        }

        foreach ($this->getMenuItems() as $item) {
            if ($item['id'] === $page->getUid()) {
                return '0.8';
            }
        }

        return '0.5';
    }

    /**
     * Return the current menu items set for the site.
     *
     * @return array
     */
    private function getMenuItems()
    {
        if (null === $this->menuItems) {
            $this->menuItems = $this->_renderer
                    ->getApplication()
                    ->getContainer()->get('cloud.global_content_factory')
                    ->getHeaderMenu()
                    ->getParamValue('items');
        }

        return $this->menuItems;
    }
}
