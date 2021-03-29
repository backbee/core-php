<?php

namespace BackBeePlanet\Importer;

use BackBeePlanet\Job\JobInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImportJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var mixed
     */
    protected $source;

    /**
     * Creates new instance of ImportJob.
     *
     * @param string                   $siteId
     * @param string                   $type
     * @param string|array<string>|int $source
     */
    public function __construct($siteId, $type, $source)
    {
        $this->siteId = $siteId;
        $this->type = $type;
        $this->source = $source;
    }

    /**
     * {@inehritdoc}
     */
    public function siteId()
    {
        return $this->siteId;
    }

    /**
     * Returns the type of the source.
     *
     * @return string
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * Returns information about how to connect to source.
     *
     * @return string|array<string>|int
     */
    public function source()
    {
        return $this->source;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(
            [
                $this->siteId,
                $this->type,
                $this->source,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        [
            $this->siteId,
            $this->type,
            $this->source
        ] = unserialize($serialized);
    }
}
