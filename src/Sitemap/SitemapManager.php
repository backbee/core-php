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

namespace BackBeePlanet\Sitemap;

use BackBee\BBApplication;
use BackBee\Cache\AbstractExtendedCache;
use BackBee\Config\Config;
use BackBee\Exception\InvalidArgumentException;
use BackBee\Util\Collection\Collection;
use DateTime;
use DateTimeZone;

/**
 * Class SitemapManager
 *
 * @package BackBeePlanet\Sitemap
 *
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SitemapManager
{
    /**
     * @var BBApplication
     */
    private $bbApp;

    /**
     * @var Config
     */
    private $config;

    /**
     * A cache engine instance.
     *
     * @var AbstractExtendedCache
     */
    private $cache;

    /**
     * Cache control directives for every sitemap.
     *
     * @var array
     */
    private $cacheControl = [];

    /**
     * SitemapManager constructor.
     *
     * @param BBApplication $bbApp
     * @param Config        $config
     */
    public function __construct(BBApplication $bbApp, Config $config)
    {
        $this->bbApp = $bbApp;
        $this->config = $config;
        $this->initCache();
    }

    /**
     * Initialize cache.
     */
    public function initCache(): void
    {
        if ($this->bbApp->getContainer()->has('cache.control')) {
            $this->cache = $this->bbApp->getContainer()->get('cache.control');
        }

        foreach ($this->config->getSection('sitemaps') ?? [] as $id => $definition) {
            $cacheControl = Collection::get($definition, 'cache-control', []);
            $this->setCacheControl($id, $cacheControl);
        }
    }

    /**
     * Sets cache control directive for sitemap $id.
     *
     * @param  string  $id           The sitemap identifier.
     * @param  array   $cacheControl Optional, an array of cache directives.
     */
    public function setCacheControl(string $id, array $cacheControl = []): void
    {
        $this->cacheControl[$id] = $cacheControl;
    }

    /**
     * Gets cache control directive for sitemap $id.
     *
     * @param string $id The sitemap identifier.
     *
     * @return array      An array of cache directives
     */
    public function getCacheControl(string $id): array
    {
        return $this->cacheControl[$id] ?? [];
    }

    /**
     * Returns the cache stored content for sitemap.
     *
     * @param string $id       A sitemap id.
     * @param string $pathInfo A path info to sitemap.
     *
     * @return mixed|false      The stored content if valid, false elsewhere.
     * @throws InvalidArgumentException
     */
    public function loadCache(string $id, string $pathInfo)
    {
        if (!$this->isCacheAvailable($id)) {
            return false;
        }

        $data = json_decode($this->cache->load($this->getCacheId($id, $pathInfo)), true);
        if (isset($data['lastmod'])) {
            $data['lastmod'] = new DateTime(
                Collection::get($data['lastmod'], 'date'),
                new DateTimeZone(Collection::get($data['lastmod'], 'timezone', ''))
            );
        }

        return $data;
    }

    /**
     * Saves a sitemap in cache.
     *
     * @param string $id       A sitemap id.
     * @param string $pathInfo A path info to sitemap.
     * @param mixed  $data     The sitemap content.
     *
     * @return boolean
     * @throws InvalidArgumentException
     */
    public function saveCache(string $id, string $pathInfo, $data): bool
    {
        if (!$this->isCacheAvailable($id)) {
            return false;
        }

        return $this->cache->save(
            $this->getCacheId($id, $pathInfo),
            json_encode($data),
            Collection::get($this->cacheControl, $id . ':max_age', 60 * 60),
            'sitemap-' . $id
        );
    }

    /**
     * Is cache is available for the sitemap?
     *
     * @param string $id A sitemap id.
     *
     * @return boolean
     * @throws InvalidArgumentException
     */
    private function isCacheAvailable(string $id): bool
    {
        return (
            !$this->bbApp->isDebugMode()
            && null !== $this->cache
            && null === Collection::get($this->cacheControl, $id . ':no-cache')
        );
    }

    /**
     * Return a cache unique identifier.
     *
     * @param  string $id       A sitemap id.
     * @param  string $pathInfo A path info to sitemap.
     *
     * @return string
     */
    private function getCacheId(string $id, string $pathInfo): string
    {
        return md5('sitemap' . $id . $pathInfo);
    }
}
