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

namespace BackBee\Controller;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\Site\Site;
use BackBee\Sitemap\SitemapManager;
use BackBee\Util\Collection\Collection;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SitemapController
 *
 * Sitemap controller, only one action method handles every request.
 *
 * @package BackBee\Controller
 */
class SitemapController
{
    /**
     * The bundle instance.
     *
     * @var SitemapManager
     */
    private $sitemapManager;

    /**
     * The current application services container.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * The current BackBee site.
     *
     * @var Site
     */
    private $site;

    /**
     * @var Config
     */
    private $config;

    /**
     * SitemapController constructor.
     *
     * @param BBApplication  $bbApp
     * @param SitemapManager $sitemapManager
     * @param Config         $config
     */
    public function __construct(BBApplication $bbApp, SitemapManager $sitemapManager, Config $config)
    {
        $this->site = $bbApp->getSite();
        $this->container = $bbApp->getContainer();
        $this->sitemapManager = $sitemapManager;
        $this->config = $config;
    }

    /**
     * Handles sitemap requests.
     *
     * @param Request $request The current request.
     *
     * @return Response
     */
    public function indexAction(Request $request): Response
    {
        return $this->getSitemap($request);
    }

    /**
     * Get sitemap.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getSitemap(Request $request): ?Response
    {
        $sitemap = null;
        $pathInfo = (string)str_replace('.gz', '', $request->getPathInfo());

        try {
            //if (!($sitemap = $this->sitemapManager->loadCache($id, $request->getPathInfo()))) {
            //$preset = $this->getPreset($decorator, $request->attributes->all());
//            $params = $this->getParams();
//            $sitemaps = $decorator->render($preset, $params);
//            if (!isset($sitemaps[$pathInfo])) {
//                return new Response('Not found', Response::HTTP_NOT_FOUND);
//            }
//
//            $sitemap = $sitemaps[$pathInfo];

            //$this->sitemapManager->saveCache($id, $request->getPathInfo(), $sitemap);
            //}

            $sitemap = $this->sitemapManager->generate();

        } catch (Exception $exception) {
            dump($exception);
            $this->container->get('logger')->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $sitemap;
    }

    /**
     * Returns an array of parameters for rendering.
     *
     * @param string $id A sitemap id.
     *
     * @return array
     * @throws Exception
     */
    private function getParams(string $id): array
    {
        $config = $this->config->getSection('sitemaps');

        return [
            'site' => $this->site,
            'step' => Collection::get($config, $id . ':iterator-step', 1500),
            'changeFreq' => $config['urlset']['change_freq'] ?? 'always',
        ];
    }
}
