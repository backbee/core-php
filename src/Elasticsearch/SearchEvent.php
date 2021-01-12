<?php

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
