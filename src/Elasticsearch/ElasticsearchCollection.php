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

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ElasticsearchCollection implements \Iterator
{
    /**
     * @var integer
     */
    protected $countMax;

    /**
     * @var array
     */
    protected $collection;

    /**
     * @var integer
     */
    protected $position;

    /**
     * @var integer
     */
    protected $start;

    /**
     * @var integer
     */
    protected $limit;

    /**
     * ElasticSearchCollection constructor.
     *
     * @param array $collection
     * @param       $countMax
     * @param int   $start
     * @param null  $limit
     */
    public function __construct(array $collection, $countMax, $start = 0, $limit = null)
    {
        $this->collection = $collection;
        $this->countMax = (int) $countMax;
        $this->start = (int) $start;
        $this->limit = $limit;
    }

    /**
     * Returns the number of item in current collection.
     *
     * @return integer
     */
    public function count(): int
    {
        return count($this->collection);
    }

    /**
     * Returns the number max of item (ignoring collection limit and start).
     *
     * @return integer
     */
    public function countMax(): int
    {
        return $this->countMax;
    }

    /**
     * Returns the whole collection.
     *
     * @return array
     */
    public function collection(): array
    {
        return $this->collection;
    }

    /**
     * Returns the index of the first element of current collection (start from 0).
     *
     * @return integer
     */
    public function start(): int
    {
        return $this->start;
    }

    /**
     * Returns the max size of the collection. Can be null if no limit provided.
     *
     * @return integer|null
     */
    public function limit(): ?int
    {
        return $this->limit;
    }

    /**
     * Returns the page of the current collection.
     *
     * @return integer
     */
    public function currentPagination()
    {
        return $this->limit && 0 < $this->start ? ($this->start / $this->limit) + 1 : 1;
    }

    /**
     * Returns max pages according to current collection criteria,max count and limit.
     *
     * Note that it can return null if limit is not defined.
     *
     * @return integer|null
     */
    public function maxPagination()
    {
        return $this->limit ? ceil($this->countMax / $this->limit) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->collection[$this->position];
    }

    /**
     * {@inheritdoc}
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return isset($this->collection[$this->position]);
    }
}
