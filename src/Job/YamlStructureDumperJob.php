<?php

namespace BackBeeCloud\Job;

use BackBeePlanet\Job\JobInterface;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class YamlStructureDumperJob implements JobInterface
{
    /**
     * @var string
     */
    protected $siteId;

    /**
     * @var string
     */
    protected $themeName;

    /**
     * @var string
     */
    protected $mail;

    /**
     * @var [type]
     */
    protected $domain;

    /**
     * Creates new instance of YamlStructureDumperJob.
     *
     * @param string $siteId
     * @param string $themeName
     * $param string $mail
     */
    public function __construct($siteId, $themeName, $mail, $domain)
    {
        $this->siteId = $siteId;
        $this->themeName = $themeName;
        $this->mail = $mail;
        $this->domain = $domain;
    }

    /**
     * {@inehritdoc}
     */
    public function siteId()
    {
        return $this->siteId;
    }

    /**
     * Return the theme name
     *
     * @return string
     */
    public function themeName()
    {
        return $this->themeName;
    }

    /**
     * Return the mail
     *
     * @return string
     */
    public function mail()
    {
        return $this->mail;
    }

     /**
     * Return the domain
     *
     * @return string
     */
    public function domain()
    {
        return $this->domain;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([$this->siteId, $this->themeName, $this->mail, $this->domain]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->siteId, $this->themeName, $this->mail, $this->domain) = unserialize($serialized);
    }
}