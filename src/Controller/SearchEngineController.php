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

namespace BackBeeCloud\Controller;

use BackBee\Renderer\Exception\RendererException;
use BackBee\Renderer\Renderer;
use BackBeeCloud\SearchEngine\SearchEngineManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SearchEngineController
 *
 * @package BackBeeCloud\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SearchEngineController
{
    /**
     * User preference data key const.
     */
    public const USER_PREFERENCE_DATA_KEY = 'search-engines';

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * @var SearchEngineManager
     */
    protected $searchEngineManager;

    /**
     * SearchEngineController constructor.
     *
     * @param Renderer            $renderer
     * @param SearchEngineManager $searchEngineManager
     */
    public function __construct(Renderer $renderer, SearchEngineManager $searchEngineManager)
    {
        $this->renderer = $renderer;
        $this->searchEngineManager = $searchEngineManager;
    }

    /**
     * @return Response
     * @throws RendererException
     */
    public function robotsTxt(): Response
    {
        $content = $this->renderer->partial('common/robots.txt.twig', [
            'do_index' => $this->searchEngineManager->googleSearchEngineIsActivated()
        ]);

        return new Response($content, Response::HTTP_OK, [
            'Accept-Ranges'  => 'bytes',
            'Content-Type'   => 'text/plain',
            'Content-Length' => strlen($content),
        ]);
    }
}
