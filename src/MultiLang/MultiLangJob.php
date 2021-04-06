<?php

namespace BackBeeCloud\MultiLang;

use BackBeePlanet\Job\JobInterface;

/**
 * Class MultiLangJob
 *
 * @package BackBeeCloud\MultiLang
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * @var string
     */
    protected $lang;

    /**
     * Creates new instance of ImportJob.
     *
     * @param string $siteId
     * @param string $lang
     */
    public function __construct(string $siteId, string $lang)
    {
        $this->siteId = $siteId;
        $this->lang = $lang;
    }

    /**
     * {@inheritdoc}
     */
    public function siteId(): string
    {
        return $this->siteId;
    }

    /**
     * Returns the lang to set as default.
     *
     * @return string
     */
    public function lang(): string
    {
        return $this->lang;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): ?string
    {
        return serialize(
            [
                $this->siteId,
                $this->lang,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        [$this->siteId, $this->lang] = unserialize($serialized);
    }
}
