<?php

namespace BackBeePlanet\Redis;

use BackBeePlanet\GlobalSettings;
use Exception;
use Predis\Client;

/**
 * Class RedisManager
 *
 * @package BackBeePlanet\Redis
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class RedisManager
{
    /**
     * @var Client
     */
    protected static $redisClient;

    /**
     * Remove page cache.
     *
     * @param string $siteId
     */
    public static function removePageCache(string $siteId): void
    {
        if (null === $client = self::getClient()) {
            return;
        }

        $settings = (new GlobalSettings())->redis();
        $client->select($settings['page_cache_db'] ?? 0);
        if ($keys = $client->keys(sprintf('%s:*', $siteId))) {
            $client->del($keys);
        }
    }

    /**
     * Get client.
     *
     * @return Client
     */
    public static function getClient(): Client
    {
        if (null === self::$redisClient) {
            $settings = (new GlobalSettings())->redis();
            $client = new Client($settings);
            try {
                $client->connect();
                $client->select($settings['page_cache_db'] ?? 0);
                self::$redisClient = $client;
            } catch (Exception $e) {
                error_log(sprintf('[%s] %s', __METHOD__, $e->getMessage()));
            }
        }

        return self::$redisClient;
    }
}
