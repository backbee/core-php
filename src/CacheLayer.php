<?php

namespace BackBeeCloud;

use BackBeeCloud\Listener\CacheListener;
use BackBeePlanet\GlobalSettings;
use BackBeePlanet\RedisManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CacheLayer
{
    public static function getCachedResponse(Request $request, $basedir)
    {
        $response = null;

        $settings = (new GlobalSettings())->redis();
        if (isset($settings['disable_page_cache']) && true === $settings['disable_page_cache']) {
            return $response;
        }

        if (!$request->cookies->has(CacheListener::COOKIE_DISABLE_CACHE) && $request->isMethod('get')) {
            $redisClient = null;

            try {
                $redisClient = RedisManager::getClient();
            } catch (\Exception $e) {
                error_log(sprintf('[%s] %s', __METHOD__, $e->getMessage()));

                return $response;
            }

            if (is_link($basedir) && readlink($basedir)) {
                $basedir = readlink($basedir);
            }

            preg_match('~/(bp[0-9]+)\.~', $basedir, $matches);
            $key = sprintf('%s:%s', $matches[1], $request->getRequestUri());
            if (false != $result = $redisClient->get($key)) {
                $response = new Response($result);
            }
        }

        return $response;
    }
}
