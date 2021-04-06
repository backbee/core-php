<?php

namespace BackBeeCloud\Job;

use BackBeePlanet\Job\JobInterface;

/**
 * Class MediaImageMigrationJob
 *
 * @package BackBeeCloud\Job
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MediaImageMigrationJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * MediaImageMigrationJob constructor.
     *
     * @param string $siteId
     */
    public function __construct(string $siteId)
    {
        $this->siteId = $siteId;
    }

    /**
     * {@inheritdoc}
     */
    public function siteId(): string
    {
        return $this->siteId;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): ?string
    {
        return serialize([$this->siteId]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        [$this->siteId] = unserialize($serialized);
    }
}
