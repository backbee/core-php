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

use BackBee\Config\Config;
use BackBee\NestedNode\Page;
use BackBee\Renderer\AbstractRenderer;
use BackBeeCloud\PageType\HomeType;
use Exception;

/**
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class sitemapHelper extends AbstractHelper
{
    /**
     * Sitemap configuration.
     *
     * @var Config
     */
    private $config;

    /**
     * sitemapHelper constructor.
     *
     * @param AbstractRenderer $renderer
     *
     * @throws Exception
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $this->config = $this->getRenderer()->getApplication()->getConfig()->getSection('sitemap');
    }

    /**
     * Is excluded.
     *
     * @param string $url
     *
     * @return bool
     */
    public function isExcluded(string $url): bool
    {
        $excluded = $this->config['excluded'] ?? [];

        return !(
            !empty($excluded) &&
            preg_match(
                '/w*(' . str_replace('/', '\/', implode('.*|', $excluded)) . ')/',
                $url
            )
        );
    }

    /**
     * Return a computed priority for a page:
     *
     *  * for Home, return 1.0
     *  * for page in the first level of the menu, return 0.8
     *  * for others return 0.5
     *
     * @param array $page
     *
     * @return string
     */
    public function getLocationPriority(array $page): string
    {
        $priority = 1 / (1 + ((int)$page['level'] * (int)$page['state'] / (Page::STATE_ONLINE + Page::STATE_HIDDEN)));

        if ((new HomeType)->uniqueName() === $page['type']) {
            $priority = '1.0';
        } elseif (1 < (int)$page['level']) {
            $priority = '0.5';
        }

        return number_format($priority, 1);
    }
}
