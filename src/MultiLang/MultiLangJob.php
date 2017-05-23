<?php

namespace BackBeeCloud\MultiLang;

use BackBeePlanet\Job\JobInterface;

/**
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
    public function __construct($siteId, $lang)
    {
        $this->siteId = $siteId;
        $this->lang = $lang;
    }

    /**
     * {@inehritdoc}
     */
    public function siteId()
    {
        return $this->siteId;
    }

    /**
     * Returns the lang to set as default.
     *
     * @return string
     */
    public function lang()
    {
        return $this->lang;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            $this->siteId,
            $this->lang,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list(
            $this->siteId,
            $this->lang
        ) = unserialize($serialized);
    }
}
