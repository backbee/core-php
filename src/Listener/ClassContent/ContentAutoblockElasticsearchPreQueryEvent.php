<?php

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
