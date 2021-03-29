<?php

namespace BackBeeCloud\Job;

use BackBeePlanet\Job\JobInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MediaImageMigrationJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

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
