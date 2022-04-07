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

namespace BackBeePlanet\Job;

use BackBee\Cache\RedisManager;
use Predis\Client;
use RuntimeException;

/**
 * Class JobManager
 *
 * @package BackBeePlanet\Job
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class JobManager
{
    public const JOBS_REDIS_KEY = 'jobs';
    public const LOW_PRIORITY_JOBS_REDIS_KEY = 'low_priority_jobs';

    /**
     * @var Client
     */
    private $redisClient;

    /**
     * JobManager constructor.
     *
     * @param RedisManager $redisClient
     */
    public function __construct(RedisManager $redisClient)
    {
        $settings = $redisClient->getConfig();
        if (!isset($settings['jobs_db'])) {
            throw new RuntimeException(
                sprintf(
                    '[%s] failed to build Redis Client because parameter "jobs_db" is missing from redis settings.',
                    __METHOD__
                )
            );
        }

        $this->redisClient = $redisClient->getClient();
        $this->redisClient->select($settings['jobs_db']);
    }

    /**
     * Pushes new job into Redis.
     *
     * @param JobInterface $job
     * @param bool         $lowPriorityFlag
     */
    public function pushJob(JobInterface $job, bool $lowPriorityFlag = false): void
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
    public function pullJob(bool $lowPriorityFlag = false): ?JobInterface
    {
        $queue = false === $lowPriorityFlag
            ? self::JOBS_REDIS_KEY
            : self::LOW_PRIORITY_JOBS_REDIS_KEY;
        $result = $this->redisClient->rpop($queue);

        return $result ? unserialize($result) : null;
    }
}
