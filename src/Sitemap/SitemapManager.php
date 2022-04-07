<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBee\Sitemap;

use BackBee\BBApplication;
use BackBee\Cache\RedisManager;
use BackBee\Config\Config;
use BackBeeCloud\Search\SearchManager;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SitemapManager
 *
 * @package BackBee\Sitemap
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SitemapManager
{
    /**
     * @var \BackBee\BBApplication
     */
    private $bbApp;

    /**
     * @var \BackBee\Config\Config
     */
    private $config;

    /**
     * @var \BackBeeCloud\Search\SearchManager
     */
    private $searchManager;

    /**
     * @var \BackBee\Cache\RedisManager
     */
    private $redisManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * SitemapManager constructor.
     *
     * @param \BackBee\BBApplication $bbApp
     * @param \BackBee\Config\Config $config
     * @param \BackBeeCloud\Search\SearchManager $searchManager
     * @param \BackBee\Cache\RedisManager $redisManager
     */
    public function __construct(
        BBApplication $bbApp,
        Config $config,
        SearchManager $searchManager,
        RedisManager $redisManager
    ) {
        $this->bbApp = $bbApp;
        $this->config = $config->getSection('sitemap');
        $this->searchManager = $searchManager;
        $this->logger = $bbApp->getLogging();
        $this->redisManager = $redisManager;
    }

    /**
     * Generate sitemap.
     *
     * @return array
     */
    public function generate(): array
    {
        try {
            $locations = $this->searchManager->getBy(
                [
                    'is_online' => true,
                    'seo_index' => true,
                ],
                0,
                $this->config['limits'] ?? 10000,
                [],
                false
            );

            $urlSet = $this->bbApp->getRenderer()->partial(
                'Sitemap/UrlSet.html.twig',
                [
                    'changeFreq' => $this->config['change_freq'],
                    'locations' => $locations->collection(),
                ]
            );
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return [
            'lastModified' => (new DateTime())->format('c'),
            'urlSet' => $urlSet ?? '',
        ];
    }

    /**
     * Builds a valid response.
     *
     * @param array $sitemap The sitemap content.
     *
     * @return Response          The valid response.
     */
    public function buildResponse(array $sitemap): Response
    {
        $response = new Response();

        try {
            $response
                ->setLastModified(new DateTime($sitemap['lastModified']['date'] ?? $sitemap['lastModified']))
                ->setContent($sitemap['urlSet'])
                ->setStatusCode(Response::HTTP_OK)
                ->headers
                ->set('content-type', 'text/xml');
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $response->headers->set('cache-control', 'no-cache');
        $response->headers->set('pragma', 'no-cache');
        $response->headers->set('expires', -1);

        return $response;
    }

    /**
     * Load cache.
     *
     * @return array|null
     */
    public function loadCache(): ?array
    {
        if (!$this->isCacheAvailable()) {
            return null;
        }

        $sitemap = $this->redisManager->getClient()->get($this->getCacheId());

        return $sitemap ? json_decode($sitemap, true) : null;
    }

    /**
     * Save sitemap into cache.
     *
     * @param array $sitemap Sitemap data.
     *
     * @return void
     */
    public function saveCache(array $sitemap): void
    {
        if ($this->isCacheAvailable()) {
            $this->redisManager->getClient()->set($this->getCacheId(), json_encode($sitemap));
            $this->redisManager->getClient()->expire($this->getCacheId(), $this->config['cache_ttl']);
        }
    }

    /**
     * Update sitemap into cache.
     *
     * @return void
     */
    public function updateCache(): void
    {
        $this->redisManager->getClient()->set($this->getCacheId(), json_encode($this->generate()));
    }

    /**
     * Delete sitemap into cache.
     *
     * @return void
     */
    public function deleteCache(): void
    {
        if ($this->redisManager->getClient()->exists($this->getCacheId())) {
            $this->redisManager->getClient()->del($this->getCacheId());
        }
    }

    /**
     * Return a cache unique identifier.
     *
     * @return string
     */
    private function getCacheId(): string
    {
        return md5('sitemap_' . $this->bbApp->getContainer()->getParameter('app_name'));
    }

    /**
     * Is cache is available for the sitemap?
     *
     * @return bool
     */
    public function isCacheAvailable(): bool
    {
        return (!$this->bbApp->isDebugMode() && null !== $this->redisManager->getClient());
    }
}
