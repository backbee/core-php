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

/**
 * Class sitemapGetImagesByPage
 *
 * @package BackBee\Renderer\Helper
 */
class sitemapGetImagesByPage extends AbstractHelper
{
    /**
     * @param Page $page
     *
     * @return array|null
     */
    public function __invoke(Page $page): ?array
    {
        $images = $this->getRenderer()
            ->getApplication()
            ->getContainer()
            ->get('elasticsearch.manager')
            ->getAllImageForAnPageByUid($page->getUid());

        return array_map(static function ($image) {
            return $image['path'] ?? '';
        }, $images);
    }
}
