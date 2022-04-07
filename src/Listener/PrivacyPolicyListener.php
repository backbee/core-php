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

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class PrivacyPolicyListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class PrivacyPolicyListener
{
    /**
     * Called on "kernel.response" event.
     *
     * @param FilterResponseEvent $event
     */
    public static function onKernelResponse(FilterResponseEvent $event): void
    {
        $app = $event->getKernel()->getApplication();
        if (
            null === $app->getBBUserToken()
            || !$app->getContainer()->getParameter('privacy_policy')
        ) {
            return;
        }

        $response = $event->getResponse();
        if (false === strpos($response->getContent(), '</body>')) {
            return;
        }

        $response->setContent(
            str_replace(
                '</body>',
                $app->getRenderer()->partial('common/privacy_policy_hook.js.twig') . '</body>',
                $response->getContent()
            )
        );
    }
}
