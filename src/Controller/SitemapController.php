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
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\Sitemap\SitemapManager;
use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SitemapController
 *
 * Sitemap controller, only one action method handles every request.
 *
 * @package BackBee\Controller
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
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
     * SitemapController constructor.
     *
     * @param BBApplication  $bbApp
     * @param SitemapManager $sitemapManager
     */
    public function __construct(BBApplication $bbApp, SitemapManager $sitemapManager)
    {
        $this->container = $bbApp->getContainer();
        $this->sitemapManager = $sitemapManager;
    }

    /**
     * Handles sitemap requests.
     *
     * @return Response
     */
    public function indexAction(): Response
    {
        try {
            if (($sitemap = $this->sitemapManager->loadCache()) === null) {
                $sitemap = $this->sitemapManager->generate();
                $this->sitemapManager->saveCache($sitemap);
            }
        } catch (Exception $exception) {
            $this->container->get('logger')->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $this->sitemapManager->buildResponse($sitemap ?? null);
    }
}
