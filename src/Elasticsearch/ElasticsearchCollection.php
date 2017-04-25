<?php

namespace BackBeeCloud\Elasticsearch;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
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
    public function count()
    {
        return count($this->collection);
    }

    /**
     * Returns the number max of item (ignoring collection limit and start).
     *
     * @return integer
     */
    public function countMax()
    {
        return $this->countMax;
    }

    /**
     * Returns the whole collection.
     *
     * @return array
     */
    public function collection()
    {
        return $this->collection;
    }

    /**
     * Returns the index of the first element of current collection (start from 0).
     *
     * @return integer
     */
    public function start()
    {
        return $this->start;
    }

    /**
     * Returns the max size of the collection. Can be null if no limit provided.
     *
     * @return integer|null
     */
    public function limit()
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
        return $this->limit && 0 < $this->start
            ? ($this->start / $this->limit) + 1
            : 1
        ;
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
    public function rewind()
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
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->position = $this->position + 1;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return isset($this->collection[$this->position]);
    }
}
