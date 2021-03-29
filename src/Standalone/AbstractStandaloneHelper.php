<?php

namespace BackBeePlanet\Standalone;

use BackBeeCloud\Listener\CacheListener;
use BackBeeCloud\UserAgentHelper;
use BackBeePlanet\GlobalSettings;
use BackBeePlanet\Redis\RedisManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractStandaloneHelper
{
    /**
     * Returns project root directory.
     *
     * /!\ This method must be overrided by any class that extends this abstact class.
     *
     * @return string
     */
    public static function rootDir()
    {
        throw new \RuntimeException(sprintf(
            '%s must be overrided by %s and never be called',
            __METHOD__,
            static::class
        ));
    }

    public static function resDir()
    {
        return realpath(static::rootDir() . DIRECTORY_SEPARATOR . 'res');
    }

    public static function distDir()
    {
        return realpath(static::resDir() . DIRECTORY_SEPARATOR . 'dist');
    }

    public static function cacheDir()
    {
        $path = static::rootDir() . DIRECTORY_SEPARATOR . 'cache';
        static::mkdirOnce($path);

        return $path;
    }

    public static function logDir()
    {
        return realpath(static::rootDir() . DIRECTORY_SEPARATOR . 'log');
    }

    public static function repositoryDir()
    {
        return realpath(static::resDir() . DIRECTORY_SEPARATOR . 'repository');
    }

    public static function configDir()
    {
        return realpath(static::repositoryDir() . DIRECTORY_SEPARATOR . 'Config');
    }

    public static function mkdirOnce($path)
    {
        $umask = umask();
        umask(0);
        if (!is_dir($path) && !mkdir($path, 0777)) {
            throw new \RuntimeException(sprintf('Error occurs while creating "%s".', $path));
        }

        umask($umask);
    }

    public static function appname()
    {
        $appName = (new GlobalSettings())->appname();
        return false === $appName ? basename(static::rootDir()) : $appName;
    }

    public static function getCachedResponse(Request $request)
    {
        $response = null;


        $settings = (new GlobalSettings())->redis();
        if (isset($settings['disable_page_cache']) && true === $settings['disable_page_cache']) {
            return $response;
        }

        $redisClient = RedisManager::getClient();
        if (!$redisClient) {
            return $response;
        }

        if (!$request->cookies->has(CacheListener::COOKIE_DISABLE_CACHE) && $request->isMethod('get')) {
            try {
                $redisClient->connect();
                $redisClient->select($settings['page_cache_db'] ?? 0);
            } catch (\Exception $e) {
                error_log(sprintf('[%s] %s', __METHOD__, $e->getMessage()));

                return $response;
            }

            $requestUri = $request->getRequestUri();
            $key = sprintf('%s:%s[%s]', static::appname(), $requestUri, UserAgentHelper::getDeviceType());
            if (false !== ($result = $redisClient->get($key))) {
                $contentType = 'text/html';
                if (1 === preg_match('~\.css$~', $requestUri)) {
                    $contentType = 'text/css';
                }

                $response = new Response($result, Response::HTTP_OK, [
                    'Content-Type' => $contentType,
                ]);
            }
        }

        return $response;
    }
}
