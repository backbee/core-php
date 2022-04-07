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

namespace BackBeePlanet\Importer;

use BackBeePlanet\Job\JobInterface;

/**
 * Class ImportJob
 *
 * @package BackBeePlanet\Importer
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImportJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var mixed
     */
    protected $source;

    /**
     * Creates new instance of ImportJob.
     *
     * @param string                   $siteId
     * @param string                   $type
     * @param string|array<string>|int $source
     */
    public function __construct(string $siteId, string $type, $source)
    {
        $this->siteId = $siteId;
        $this->type = $type;
        $this->source = $source;
    }

    /**
     * {@inehritdoc}
     */
    public function siteId(): string
    {
        return $this->siteId;
    }

    /**
     * Returns the type of the source.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Returns information about how to connect to source.
     *
     * @return string|array<string>|int
     */
    public function source()
    {
        return $this->source;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): ?string
    {
        return serialize(
            [
                $this->siteId,
                $this->type,
                $this->source,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        [$this->siteId, $this->type, $this->source] = unserialize($serialized);
    }
}
