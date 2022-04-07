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

namespace BackBeeCloud\Elasticsearch;

use ArrayObject;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchEvent extends Event
{
    /**
     * @var ArrayObject
     */
    protected $queryBody;

    /**
     * @var ArrayObject
     */
    protected $size;

    /**
     * @var ArrayObject
     */
    protected $orderBy;

    /**
     * Constructor.
     *
     * Transforms provided array into \ArrayObject to allow update of query body
     * without using setter.
     *
     * @param array $queryBody
     */
    public function __construct(array $queryBody)
    {
        $this->queryBody = new ArrayObject($queryBody);
    }

    /**
     * Returns query body.
     *
     * @return ArrayObject
     */
    public function getQueryBody()
    {
        return $this->queryBody;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setSize($size)
    {
        $this->size = (int)$size;
    }

    public function getOrderBy()
    {
        return $this->orderBy;
    }

    public function setOrderBy(array $orderBy)
    {
        $this->orderBy = $orderBy;
    }
}
