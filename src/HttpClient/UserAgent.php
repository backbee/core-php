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

namespace BackBee\HttpClient;

use Jenssegers\Agent\Agent;

/**
 * Class UserAgent
 *
 * @package BackBeeCloud
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserAgent
{
    /**
     * @var Agent
     */
    protected static $agent;

    /**
     * @var bool
     */
    protected static $isDesktop;

    /**
     * @var bool
     */
    protected static $isMobile;

    /**
     * @var bool
     */
    protected static $isTablet;

    /**
     * Is desktop support.
     *
     * @return bool
     */
    public static function isDesktop(): bool
    {
        if (true === self::$isMobile || true === self::$isTablet) {
            return false;
        }

        if (null === self::$isDesktop) {
            self::$isDesktop = self::getAgent()->isDesktop();
            if (self::$isDesktop) {
                self::$isTablet = false;
                self::$isMobile = self::$isTablet;
            }
        }

        return self::$isDesktop;
    }

    /**
     * Is mobile support.
     *
     * @return bool
     */
    public static function isMobile(): bool
    {
        if (true === self::$isDesktop || true === self::$isTablet) {
            return false;
        }

        if (null === self::$isMobile) {
            self::$isMobile = self::getAgent()->isMobile();
            if (self::$isMobile) {
                self::$isTablet = false;
                self::$isDesktop = self::$isTablet;
            }
        }

        return self::$isMobile;
    }

    /**
     * Is tablet support.
     *
     * @return bool
     */
    public static function isTablet(): bool
    {
        if (true === self::$isDesktop || true === self::$isMobile) {
            return false;
        }

        if (null === self::$isTablet) {
            self::$isTablet = self::getAgent()->isTablet();
            if (self::$isTablet) {
                self::$isMobile = false;
                self::$isDesktop = self::$isMobile;
            }
        }

        return self::$isTablet;
    }

    /**
     * Get device type.
     *
     * @return string
     */
    public static function getDeviceType(): string
    {
        return self::isDesktop() ? 'desktop' : (self::isTablet() ? 'tablet' : 'mobile');
    }

    /**
     * Get agent.
     *
     * @return Agent
     */
    protected static function getAgent(): Agent
    {
        return self::$agent ?? new Agent();
    }
}
