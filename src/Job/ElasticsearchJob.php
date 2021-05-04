<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

/**
 * Class ElasticsearchJob
 *
 * @package BackBeePlanet\Job
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class ElasticsearchJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * Creates new instance of ElasticsearchJob.
     *
     * @param string $siteId
     */
    public function __construct(string $siteId)
    {
        $this->siteId = $siteId;
    }

    /**
     * {@inheritdoc}
     */
    public function siteId(): string
    {
        return $this->siteId;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): ?string
    {
        return serialize([$this->siteId]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        [$this->siteId] = unserialize($serialized);
    }
}
