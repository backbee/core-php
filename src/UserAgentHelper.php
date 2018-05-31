<?php

namespace BackBeeCloud;

use Jenssegers\Agent\Agent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserAgentHelper
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

    public static function isDesktop()
    {
        if (true === self::$isMobile || true === self::$isTablet) {
            return false;
        }

        if (null === self::$isDesktop) {
            self::$isDesktop = self::getAgent()->isDesktop();
            if (self::$isDesktop) {
                self::$isMobile = self::$isTablet = false;
            }
        }

        return self::$isDesktop;
    }

    public static function isMobile()
    {
        if (true === self::$isDesktop || true === self::$isTablet) {
            return false;
        }

        if (null === self::$isMobile) {
            self::$isMobile = self::getAgent()->isMobile();
            if (self::$isMobile) {
                self::$isDesktop = self::$isTablet = false;
            }
        }

        return self::$isMobile;
    }

    public static function isTablet()
    {
        if (true === self::$isDesktop || true === self::$isMobile) {
            return false;
        }

        if (null === self::$isTablet) {
            self::$isTablet = self::getAgent()->isTablet();
            if (self::$isTablet) {
                self::$isDesktop = self::$isMobile = false;
            }
        }

        return self::$isTablet;
    }

    public static function getDeviceType()
    {
        return self::isDesktop() ? 'desktop' : (self::isTablet() ? 'tablet' : 'mobile');
    }

    protected static function getAgent()
    {
        if (null === self::$agent) {
            self::$agent = new Agent();
        }

        return self::$agent;
    }
}
