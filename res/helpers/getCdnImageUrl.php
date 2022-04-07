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

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getCdnImageUrl extends getCdnUri
{
    /**
     * Cdn settings key.
     */
    public const CDN_SETTINGS_KEY = 'image_domain';

    /**
     * @param       $uri
     * @param false $preserveScheme
     *
     * @return string|null
     */
    public function __invoke($uri, $preserveScheme = false): ?string
    {
        if ('' === $uri) {
            return null;
        }

        $path = parse_url($uri, PHP_URL_PATH);

        $url = $this->_renderer->getUri(
            str_replace(
                '/images',
                '',
                false === strpos($path, '/') ? '/' . $path : $path
            ),
            null,
            $this->site
        );

        return false === $preserveScheme ? preg_replace('~https?:~', '', $url) : $url;
    }
}
