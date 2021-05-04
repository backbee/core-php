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
use function number_format;

/**
 * Class sitemapLocationPriority
 *
 * Helper computing a priority for pages in sitemaps.
 *
 * @package BackBee\Renderer\Helper
 */
class sitemapLocationPriority extends AbstractHelper
{
    /**
     * Return a computed priority for a page:
     *  * for Home, return 1.0
     *  * for page in the first level of the menu, return 0.8
     *  * for others return 0.5
     *
     * @param Page|null $page
     *
     * @return string
     */
    public function __invoke(Page $page = null): string
    {
        if (null === $page || 1 < $page->getLevel()) {
            return '0.5';
        }

        $priority = 1 / (1 + ($page->getLevel() * $page->getState() / (Page::STATE_ONLINE + Page::STATE_HIDDEN)));

        return number_format($priority, 1);
    }
}
