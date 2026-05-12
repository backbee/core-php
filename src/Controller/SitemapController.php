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

namespace BackBee\Controller;

use BackBee\ApplicationInterface;
use BackBee\Sitemap\SitemapManager;
use Symfony\Component\HttpFoundation\Response;
use function sprintf;

/**
 * Class SitemapController
 *
 * Sitemap controller, only one action method handles every request.
 *
 * @package BackBee\Controller
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SitemapController extends Controller
{
    /**
     * The bundle instance.
     *
     * @var SitemapManager
     */
    private SitemapManager $sitemapManager;

    /**
     * Constructor.
     *
     * @param \BackBee\ApplicationInterface   $application
     * @param \BackBee\Sitemap\SitemapManager $sitemapManager
     */
    public function __construct(ApplicationInterface $application, SitemapManager $sitemapManager)
    {
        parent::__construct($application);
        $this->sitemapManager = $sitemapManager;
    }

    /**
     * Handles sitemap requests.
     *
     * @return Response
     */
    public function indexAction(): Response
    {
        if ($this->getApplication()->getSession()->isStarted()) {
            $this->getApplication()->getSession()->save();
        }

        $sitemap = null;

        try {
            $sitemap = $this->sitemapManager->loadCache();

            if ($sitemap === null) {
                $sitemap = $this->sitemapManager->generate();
                $this->sitemapManager->saveCache($sitemap);
            }
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $this->sitemapManager->buildResponse($sitemap);
    }
}
