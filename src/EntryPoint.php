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

namespace BackBee;

use BackBee\Bundle\AbstractBundle;
use BackBee\Config\Config;
use BackBeeCloud\Translation\HasTranslatableResourceInterface;
use Exception;
use function dirname;

/**
 * Class EntryPoint
 *
 * @package BackBeeCloud
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class EntryPoint extends AbstractBundle implements HasTranslatableResourceInterface
{
    /**
     * On load events.
     *
     * @param BBApplication $app
     * @param Config        $config
     *
     * @throws Exception
     */
    public static function onLoadEvents(BBApplication $app, Config $config): void
    {
        $app->getConfig()->setSection(
            'events',
            $config->getRawSection('events'),
            true
        );

        $app->getEventDispatcher()->clearAllListeners();
        $app->getEventDispatcher()->addListeners($config->getRawSection('events'));
    }

    /**
     * On load configurations.
     *
     * @param BBApplication $app
     * @param Config        $config
     *
     * @throws Exception
     */
    public static function onLoadConfigurations(BBApplication $app, Config $config): void
    {
        // Sitemap
        $app->getConfig()->setSection(
            'sitemap',
            array_replace_recursive(
                $config->getRawSection('sitemap') ?? [],
                $app->getConfig()->getSection('sitemap') ?? []
            )
        );

        // Knowledge Graph
        $app->getConfig()->setSection(
            'knowledge_graph',
            array_replace_recursive(
                $config->getRawSection('knowledge_graph') ?? [],
                $app->getConfig()->getSection('knowledge_graph') ?? []
            )
        );

        // Optimize image
        $app->getConfig()->setSection(
            'optimize_image',
            array_replace_recursive(
                $config->getRawSection('optimize_image') ?? [],
                $app->getConfig()->getSection('optimize_image') ?? []
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return $this;
    }

    /**
     * Get translation directory.
     *
     * @return string
     */
    public function getTranslationDirectory(): string
    {
        return dirname($this->getBaseDirectory()) . '/res/translations';
    }
}
