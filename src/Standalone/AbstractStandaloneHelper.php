<?php

namespace BackBeePlanet\Standalone;

use BackBee\Config\Config;
use BackBeeCloud\Listener\CacheListener;
use BackBee\HttpClient\UserAgent;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AbstractStandaloneHelper
 *
 * @package BackBeePlanet\Standalone
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractStandaloneHelper
{
    /**
     * Returns project root directory.
     *
     * /!\ This method must be overrides by any class that extends this abstract class.
     *
     * @return string
     */
    public static function rootDir()
    {
        throw new RuntimeException(
            sprintf(
                '%s must be overrided by %s and never be called',
                __METHOD__,
                static::class
            )
        );
    }

    /**
     * Get res directory.
     *
     * @return false|string
     */
    public static function resDir()
    {
        return realpath(static::rootDir() . DIRECTORY_SEPARATOR . 'res');
    }

    /**
     * Get dist directory.
     *
     * @return false|string
     */
    public static function distDir()
    {
        return realpath(static::resDir() . DIRECTORY_SEPARATOR . 'dist');
    }

    /**
     * Get cache directory.
     *
     * @return string
     */
    public static function cacheDir(): string
    {
        $path = static::rootDir() . DIRECTORY_SEPARATOR . 'cache';
        static::mkdirOnce($path);

        return $path;
    }

    /**
     * Get log directory.
     *
     * @return false|string
     */
    public static function logDir()
    {
        return realpath(static::rootDir() . DIRECTORY_SEPARATOR . 'log');
    }

    /**
     * Get repository dir.
     *
     * @return false|string
     */
    public static function repositoryDir()
    {
        return realpath(static::resDir() . DIRECTORY_SEPARATOR . 'repository');
    }

    /**
     * Get config directory.
     *
     * @return false|string
     */
    public static function configDir()
    {
        return realpath(static::repositoryDir() . DIRECTORY_SEPARATOR . 'Config');
    }

    /**
     * Mkdir once.
     *
     * @param $path
     */
    public static function mkdirOnce($path)
    {
        $umask = umask();
        umask(0);
        if (!is_dir($path) && !mkdir($path, 0777)) {
            throw new RuntimeException(sprintf('Error occurs while creating "%s".', $path));
        }

        umask($umask);
    }

    /**
     * Get app name.
     *
     * @param Config $config
     *
     * @return string
     */
    public static function appName(Config $config): string
    {
        $app = $config->getSection('app');
        return $app['name'] ?? basename(static::rootDir());
    }

    /**
     * Get cached response.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return Response|null
     */
    public static function getCachedResponse(Application $app, Request $request): ?Response
    {
        $redisClient = $app->getContainer()->get('core.redis.manager')->getClient();
        $response = null;

        try {
            $settings = $app->getConfig()->getSection('redis');

            if (
                (isset($settings['disable_page_cache']) && true === $settings['disable_page_cache']) ||
                null === $redisClient
            ) {
                return null;
            }
        } catch (Exception $exception) {
            $app->getLogging()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        if (!$request->cookies->has(CacheListener::COOKIE_DISABLE_CACHE) && $request->isMethod('get')) {
            try {
                $redisClient->connect();
                $redisClient->select($settings['page_cache_db'] ?? 0);
            } catch (Exception $exception) {
                $app->getLogging()->error(
                    sprintf(
                        '%s : %s :%s',
                        __CLASS__,
                        __FUNCTION__,
                        $exception->getMessage()
                    )
                );
                return null;
            }

            $requestUri = $request->getRequestUri();
            $key = sprintf(
                '%s:%s[%s]',
                static::appName($app->getConfig()),
                $requestUri,
                UserAgent::getDeviceType()
            );
            if ($result = $redisClient->get($key)) {
                $contentType = 'text/html';
                if (1 === preg_match('~\.css$~', $requestUri)) {
                    $contentType = 'text/css';
                }

                $response = new Response(
                    $result, Response::HTTP_OK, [
                        'Content-Type' => $contentType,
                    ]
                );
            }
        }

        return $response;
    }
}
