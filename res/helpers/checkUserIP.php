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

use function in_array;

/**
 * Class checkUserIP
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class checkUserIP extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @return string
     */
    public function __invoke(): string
    {
        $settings = $this->getRenderer()->getApplication()->getConfig()->getSection('whitelist');

        return (null === $settings || empty($settings)) || in_array($this->getIP(), $settings, true);
    }

    /**
     * Get IP.
     *
     * @return string
     */
    private function getIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}
