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

namespace BackBee\Sitemap;

use BackBee\BBApplication;
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
     * @var BBApplication
     */
    private $bbApp;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var SearchManager
     */
    private $searchManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * SitemapManager constructor.
     *
     * @param BBApplication $bbApp
     * @param Config $config
     * @param \BackBeeCloud\Search\SearchManager $searchManager
     */
    public function __construct(BBApplication $bbApp, Config $config, SearchManager $searchManager)
    {
        $this->bbApp = $bbApp;
        $this->config = $config->getSection('sitemap');
        $this->searchManager = $searchManager;
        $this->logger = $bbApp->getLogging();
    }

    /**
     * Generate sitemap.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generate(): Response
    {
        try {
            $locations = $this->searchManager->getBy(
                [
                    'is_online' => true,
                    'seo_index' => true
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

        return $this->buildResponse([
            'lastModified' => new DateTime(),
            'urlSet' => $urlSet ?? '',
        ]);
    }

    /**
     * Builds a valid response.
     *
     * @param array $sitemap The sitemap content.
     *
     * @return Response          The valid response.
     */
    private function buildResponse(array $sitemap): Response
    {
        $response = new Response();
        $response
            ->setLastModified($sitemap['lastModified'])
            ->setContent($sitemap['urlSet'])
            ->setStatusCode(Response::HTTP_OK)
            ->headers
            ->set('content-type', 'text/xml');

        $response->headers->set('cache-control', 'no-cache');
        $response->headers->set('pragma', 'no-cache');
        $response->headers->set('expires', -1);

        return $response;
    }
}
