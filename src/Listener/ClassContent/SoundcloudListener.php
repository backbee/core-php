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

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class SoundcloudListener
{
    /**
     * Soundcloud onRender event
     *
     * @param RendererEvent $event
     * @return void
     */
    public static function onRender(RendererEvent $event)
    {
        $renderer = $event->getRenderer();
        $content = $event->getTarget();

        $url = $content->getParamValue('url');

        if (false == $url) {
            return;
        }

        $isValidUrl = false;
        $urlData = parse_url($url);
        if (isset($urlData['host']) && isset($urlData['path'])) {
            $isValidUrl = $urlData['host'] === 'soundcloud.com' && $urlData['path'] != false;
        }

        $renderer->assign('valid_url', $isValidUrl);
    }
}
