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

namespace BackBeePlanet\Controller;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\Exception\InvalidArgumentException;
use BackBee\Site\Site;
use BackBee\Util\Collection\Collection;
use BackBeePlanet\Listener\SitemapListener;
use BackBeePlanet\Sitemap\Decorator\DecoratorInterface;
use BackBeePlanet\Sitemap\SitemapManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SitemapController
 *
 * Sitemap controller, only one action method handles every request.
 *
 * @package BackBeePlanet\Controller
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
     * @throws InvalidArgumentException
     */
    public function indexAction(Request $request): Response
    {
        $id = str_replace(SitemapListener::$ROUTE_PREFIX, '', $request->attributes->get('_route'));
        $decorator = $this->getDecorator($id);

        if (null === $decorator) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        return $this->buildResponse($this->getSitemap($id, $decorator, $request));
    }

    /**
     * Get archive action.
     *
     * @param Request $request
     *
     * @return Response
     * @throws InvalidArgumentException
     */
    public function getArchiveAction(Request $request): Response
    {
        $id = str_replace(SitemapListener::$ARCHIVE_ROUTE_PREFIX, '', $request->attributes->get('_route'));
        $decorator = $this->getDecorator($id);

        if (null === $decorator) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        $sitemap = $this->getSitemap($id, $decorator, $request);

        $response = new Response();
        $response
            ->setContent(gzencode($sitemap['urlset'], 9))
            ->setStatusCode(Response::HTTP_OK)
            ->headers
            ->set('content-type', 'application/gzip');
        $response->headers->set('cache-control', 'no-cache');
        $response->headers->set('pragma', 'no-cache');
        $response->headers->set('expires', -1);

        return $response;
    }

    /**
     * Get sitemap.
     *
     * @param string             $id
     * @param DecoratorInterface $decorator
     * @param Request            $request
     *
     * @return false|mixed|Response
     * @throws InvalidArgumentException
     */
    private function getSitemap(string $id, DecoratorInterface $decorator, Request $request)
    {
        $pathInfo = (string)str_replace('.gz', '', $request->getPathInfo());

        if (!($sitemap = $this->sitemapManager->loadCache($id, $request->getPathInfo()))) {
            $preset = $this->getPreset($decorator, $request->attributes->all());
            $params = $this->getParams($id);
            $sitemaps = $decorator->render($preset, $params);
            if (!isset($sitemaps[$pathInfo])) {
                return new Response('Not found', Response::HTTP_NOT_FOUND);
            }

            $sitemap = $sitemaps[$pathInfo];

            $this->sitemapManager->saveCache($id, $request->getPathInfo(), $sitemap);
        }

        return $sitemap;
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
            ->setLastModified($sitemap['lastmod'])
            ->setContent($sitemap['urlset'])
            ->setStatusCode(Response::HTTP_OK)
            ->headers
            ->set('content-type', 'text/xml');

        $response->headers->set('cache-control', 'no-cache');
        $response->headers->set('pragma', 'no-cache');
        $response->headers->set('expires', -1);

        return $response;
    }

    /**
     * Returns the decorator associated to a route name.
     *
     * @param string $id A sitemap id.
     *
     * @return DecoratorInterface|object|null
     *                                     if found, null otherwise.
     */
    private function getDecorator(string $id)
    {
        return $this->container->has(SitemapListener::$DECORATOR_PREFIX . $id) ?
            $this->container->get(SitemapListener::$DECORATOR_PREFIX . $id) : null;
    }

    /**
     * Returns the preset parameters from the request attributes.
     *
     * @param DecoratorInterface $decorator  The decorator.
     * @param array              $attributes The request attributes.
     *
     * @return array                         An array of accepted preset discriminators.
     */
    private function getPreset(DecoratorInterface $decorator, array $attributes): array
    {
        return array_intersect_key($attributes, array_flip($decorator->getCollector()->getAcceptedDiscriminators()));
    }

    /**
     * Returns an array of parameters for rendering.
     *
     * @param string $id A sitemap id.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function getParams(string $id): array
    {
        return [
            'site' => $this->site,
            'step' => Collection::get($this->config->getSection('sitemaps'), $id . ':iterator-step', 1500),
        ];
    }
}
