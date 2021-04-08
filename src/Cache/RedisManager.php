<?php

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
     * AbstractStandaloneHelper constructor.
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
     * Get client.
     *
     * @return null|Client
     */
    public function getClient(): ?Client
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
