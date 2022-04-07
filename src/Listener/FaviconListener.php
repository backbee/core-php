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

namespace BackBeeCloud\Listener;

use BackBee\Renderer\Exception\RendererException;
use BackBee\Renderer\Renderer;
use BackBeeCloud\UserPreference\UserPreferenceManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class FaviconListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class FaviconListener
{
    public const FAVICON_LOCATION_TAG = '<!--##FAVICON_SPOT##-->';

    /**
     * @var UserPreferenceManager
     */
    protected static $userPreferenceManager;

    /**
     * @var Renderer
     */
    protected static $renderer;

    /**
     * FaviconListener constructor.
     *
     * @param UserPreferenceManager $userPreferenceManager
     * @param Renderer              $renderer
     */
    public function __construct(UserPreferenceManager $userPreferenceManager, Renderer $renderer)
    {
        self::$userPreferenceManager = $userPreferenceManager;
        self::$renderer = $renderer;
    }

    /**
     * On kernel response.
     *
     * @param FilterResponseEvent $event
     *
     * @throws RendererException
     */
    public function onKernelResponse(FilterResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($response instanceof JsonResponse) {
            return;
        }

        $response = $event->getResponse();
        if (false === strpos($response->getContent(), self::FAVICON_LOCATION_TAG)) {
            return;
        }

        if ($faviconData = self::$userPreferenceManager->dataOf('favicon')) {
            $response->setContent(
                str_replace(
                    self::FAVICON_LOCATION_TAG,
                    self::$renderer->partial(
                        'common/_favicon_part.html.twig',
                        array_map(
                            static function ($url) {
                                return str_replace(['http:', 'https:'], '', $url);
                            },
                            $faviconData
                        )
                    ),
                    $response->getContent()
                )
            );
        }
    }
}
