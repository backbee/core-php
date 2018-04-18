<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\ClassContent\ContentAutoblock;
use BackBee\Event\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentAutoblockElasticsearchPreQueryEvent extends Event
{
    const EVENT_NAME = 'contentautoblock.elasticsearch.prequery';

    /**
     * @var ContentAutoblock
     */
    private $content;

    /**
     * @var \ArrayObject
     */
    private $esQuery;

    public function __construct(ContentAutoblock $content, \ArrayObject $esQuery)
    {
        parent::__construct($content, [$esQuery]);

        $this->content = $content;
        $this->esQuery = $esQuery;
    }

    public function getContentAutoblock()
    {
        return $this->content;
    }

    public function getElasticsearchQuery()
    {
        return $this->esQuery;
    }
}
