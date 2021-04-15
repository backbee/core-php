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

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\AbstractRenderer;
use BackBee\Site\Site;
use Exception;

/**
 * Class getCdnUri
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getCdnUri extends AbstractHelper
{
    public const CDN_SETTINGS_KEY = 'static_domain';

    /**
     * @var Site|null
     */
    protected $site;

    /**
     * getCdnUri constructor.
     *
     * @param AbstractRenderer $renderer
     *
     * @throws Exception
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $settings = $renderer->getApplication()->getContainer()->getParameter('cdn');

        if (isset($settings[static::CDN_SETTINGS_KEY]) && false !== $settings[static::CDN_SETTINGS_KEY]) {
            $this->site = new Site();
            $this->site->setServerName(str_replace('http://', '', $settings[static::CDN_SETTINGS_KEY]));
        }
    }

    /**
     * Invoke.
     *
     * @param      $uri
     * @param bool $preserveScheme
     *
     * @return string|string[]|null
     */
    public function __invoke($uri, bool $preserveScheme = false)
    {
        $url = $this->_renderer->getUri($uri, null, $this->site);

        return false === $preserveScheme ? preg_replace('~https?:~', '', $url) : $url;
    }
}
