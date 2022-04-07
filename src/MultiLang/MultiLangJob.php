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

namespace BackBeeCloud\MultiLang;

use BackBeePlanet\Job\JobInterface;

/**
 * Class MultiLangJob
 *
 * @package BackBeeCloud\MultiLang
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * @var string
     */
    protected $lang;

    /**
     * Creates new instance of ImportJob.
     *
     * @param string $siteId
     * @param string $lang
     */
    public function __construct(string $siteId, string $lang)
    {
        $this->siteId = $siteId;
        $this->lang = $lang;
    }

    /**
     * {@inheritdoc}
     */
    public function siteId(): string
    {
        return $this->siteId;
    }

    /**
     * Returns the lang to set as default.
     *
     * @return string
     */
    public function lang(): string
    {
        return $this->lang;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): ?string
    {
        return serialize(
            [
                $this->siteId,
                $this->lang,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        [$this->siteId, $this->lang] = unserialize($serialized);
    }
}
