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

namespace BackBeeCloud\Listener\ClassContent;

use ArrayObject;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\Event\Event;

/**
 * Class ContentAutoblockElasticsearchPreQueryEvent
 *
 * @package BackBeeCloud\Listener\ClassContent
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentAutoblockElasticsearchPreQueryEvent extends Event
{
    public const EVENT_NAME = 'contentautoblock.elasticsearch.prequery';

    /**
     * @var ContentAutoblock
     */
    private $content;

    /**
     * @var ArrayObject
     */
    private $esQuery;

    /**
     * ContentAutoblockElasticsearchPreQueryEvent constructor.
     *
     * @param ContentAutoblock $content
     * @param ArrayObject      $esQuery
     */
    public function __construct(ContentAutoblock $content, ArrayObject $esQuery)
    {
        parent::__construct($content, [$esQuery]);

        $this->content = $content;
        $this->esQuery = $esQuery;
    }

    /**
     * Get content auto block.
     *
     * @return ContentAutoblock
     */
    public function getContentAutoblock(): ContentAutoblock
    {
        return $this->content;
    }

    /**
     * Get elastic search query.
     *
     * @return ArrayObject
     */
    public function getElasticsearchQuery(): ArrayObject
    {
        return $this->esQuery;
    }
}
