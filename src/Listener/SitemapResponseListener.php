<?php
/*
 * Copyright (c) 2026 Obione
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

namespace BackBee\Listener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class SitemapResponseListener
 *
 * @author Djoudi Bensid <d.bensid@team-one.fr>
 */
class SitemapResponseListener
{
    /**
     * On kernel response.
     *
     * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
     *
     * @return void
     */
    public function onKernelResponse(FilterResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (strpos($request->getPathInfo(), 'sitemap') === false) {
            return;
        }

        $response = $event->getResponse();

        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setSharedMaxAge(86400);
        $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=86400');
        $response->headers->remove('pragma');
        $response->headers->remove('expires');

        $response->headers->remove('set-cookie');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        header_remove('Set-Cookie');
    }
}