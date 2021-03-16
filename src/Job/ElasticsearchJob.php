<?php

namespace BackBeePlanet\Job;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ElasticsearchJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * Creates new instance of ElasticsearchJob.
     *
     * @param string $siteId
     */
    public function __construct($siteId)
    {
        $this->siteId = $siteId;
    }

    /**
     * {@inehritdoc}
     */
    public function siteId()
    {
        return $this->siteId;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([$this->siteId]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        [$this->siteId] = unserialize($serialized);
    }
}
