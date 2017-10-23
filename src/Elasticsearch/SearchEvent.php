<?php

namespace BackBeeCloud\Elasticsearch;

use Symfony\Component\EventDispatcher\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchEvent extends Event
{
    /**
     * @var \ArrayObject
     */
    protected $queryBody;

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
        $this->queryBody = new \ArrayObject($queryBody);
    }

    /**
     * Returns query body.
     *
     * @return \ArrayObject
     */
    public function getQueryBody()
    {
        return $this->queryBody;
    }
}
