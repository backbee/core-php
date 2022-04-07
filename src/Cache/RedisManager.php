<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBee\Cache;

use BackBee\Config\Config;
use Exception;
use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Class RedisManager
 *
 * @package BackBee\Cache
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class RedisManager
{
    /**
     * @var Client
     */
    private $redisClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * RedisManager constructor.
     *
     * @param Config          $config
     * @param LoggerInterface $logger
     */
    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config->getSection('redis');
    }

    /**
     * Remove page cache.
     *
     * @param string $siteId
     */
    public function removePageCache(string $siteId): void
    {
        if (null === $client = $this->getClient()) {
            return;
        }

        $settings = $this->getConfig();
        $client->select($settings['page_cache_db'] ?? 0);
        if ($keys = $client->keys(sprintf('%s:*', $siteId))) {
            $client->del($keys);
        }
    }

    /**
     * Get redis client.
     *
     * @return \Predis\Client
     */
    public function getClient(): Client
    {
        if (null === $this->redisClient) {
            $settings = $this->getConfig();
            $client = new Client($settings);
            try {
                $client->connect();
                $client->select($settings['page_cache_db'] ?? 0);
                $this->redisClient = $client;
            } catch (Exception $exception) {
                $this->logger->error(
                    sprintf(
                        '%s : %s :%s',
                        __CLASS__,
                        __FUNCTION__,
                        $exception->getMessage()
                    )
                );
            }
        }

        return $this->redisClient;
    }
}
