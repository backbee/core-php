<?php

namespace BackBeePlanet\Job;

use BackBeePlanet\GlobalSettings;
use BackBeePlanet\Redis\RedisManager;
use Predis\Client;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class JobManager
{
    const JOBS_REDIS_KEY = 'jobs';
    const LOW_PRIORITY_JOBS_REDIS_KEY = 'low_priority_jobs';

    /**
     * @var Client
     */
    protected $redisClient;

    public function __construct()
    {
        $settings = (new GlobalSettings())->redis();
        if (!isset($settings['jobs_db'])) {
            throw new \RuntimeException(
                sprintf(
                    '[%s] failed to build Redis Client because parameter "jobs_db" is missing from redis settings.',
                    __METHOD__
                )
            );
        }

        $this->redisClient = RedisManager::getClient();
        $this->redisClient->select($settings['jobs_db']);
    }

    /**
     * Pushes new job into Redis.
     *
     * @param JobInterface $job
     * @param bool         $lowPriorityFlag
     */
    public function pushJob(JobInterface $job, $lowPriorityFlag = false)
    {
        $this->redisClient->lpush(
            false === $lowPriorityFlag ? self::JOBS_REDIS_KEY : self::LOW_PRIORITY_JOBS_REDIS_KEY,
            serialize($job)
        );
    }

    /**
     * Pulls a job from Redis and returns it if exists.
     *
     * @param bool $lowPriorityFlag
     *
     * @return JobInterface|null
     */
    public function pullJob($lowPriorityFlag = false)
    {
        $queue = false === $lowPriorityFlag
            ? self::JOBS_REDIS_KEY
            : self::LOW_PRIORITY_JOBS_REDIS_KEY;
        $result = $this->redisClient->rpop($queue);

        return $result ? unserialize($result) : null;
    }
}
