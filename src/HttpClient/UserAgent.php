<?php

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
