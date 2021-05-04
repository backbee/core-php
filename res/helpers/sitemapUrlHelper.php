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
use Exception;

/**
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class sitemapUrlHelper extends AbstractHelper
{
    /**
     * Pattern excluded.
     *
     * @var array
     */
    private $excluded;

    /**
     * @var bool
     */
    private $forceUrlExtension;

    /**
     * sitemapUrlHelper constructor.
     *
     * @param AbstractRenderer $renderer
     *
     * @throws Exception
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $bbApp = $this->getRenderer()->getApplication();
        $this->excluded = $bbApp->getConfig()->getSitemapsConfig('excluded') ?? [];
        $this->forceUrlExtension = $bbApp->getConfig()->getParametersConfig('force_url_extension') ?? false;
    }

    /**
     * Is excluded.
     *
     * @param string $url
     *
     * @return bool
     */
    public function isExcluded(string $url): bool
    {
        return !(
            !empty($this->excluded) &&
            preg_match(
                '/w*(' . str_replace('/', '\/', implode('.*|', $this->excluded)) . ')/',
                $url
            )
        );
    }

    /**
     * Is url extension required.
     *
     * @param string $url
     *
     * @return string
     */
    public function isUrlExtensionRequired(string $url): string
    {
        return !$this->forceUrlExtension ? str_replace('.html', '', $url) : $url;
    }
}
