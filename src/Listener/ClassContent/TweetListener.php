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

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\Renderer\Event\RendererEvent;

use GuzzleHttp\Client;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class TweetListener
{
    /**
     * Tweet onRender event
     *
     * @param RendererEvent $event
     * @return void
     */
    public static function onRender(RendererEvent $event)
    {
        $renderer = $event->getRenderer();
        $content = $event->getTarget();

        $url = $content->getParamValue('url');
        $limit = (int) $content->getParamValue('limit');

        if (false == $url) {
            return;
        }

        $urlData = parse_url($url);

        if ($urlData['host'] === 'twitter.com') {
            try {
                $res = (new Client())->request('GET', 'https://publish.twitter.com/oembed?url=' . urlencode($url) . '&limit=' . $limit . '', [
                    'headers' => [
                        'Accept'     => 'application/json',
                    ]
                ]);

                $data = json_decode((string) $res->getBody());

                $renderer->assign('html', $data->html);
            } catch (\Exception $e) {
                // nothing to do
            }
        }
    }
}