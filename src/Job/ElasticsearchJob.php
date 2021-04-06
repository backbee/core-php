<?php

namespace BackBeePlanet\Job;

/**
 * Class ElasticsearchJob
 *
 * @package BackBeePlanet\Job
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
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
