<?php

namespace BackBeeCloud\Job;

use BackBeePlanet\Job\JobInterface;

/**
 * Class YamlStructureDumperJob
 *
 * @package BackBeeCloud\Job
 *
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
     * @param string $mail
     * @param string $domain
     */
    public function __construct(string $siteId, string $themeName, string $mail, string $domain)
    {
        $this->siteId = $siteId;
        $this->themeName = $themeName;
        $this->mail = $mail;
        $this->domain = $domain;
    }

    /**
     * {@inheritDoc}
     */
    public function siteId(): string
    {
        return $this->siteId;
    }

    /**
     * Return the theme name
     *
     * @return string
     */
    public function themeName(): string
    {
        return $this->themeName;
    }

    /**
     * Return the mail
     *
     * @return string
     */
    public function mail(): string
    {
        return $this->mail;
    }

    /**
     * Return the domain
     *
     * @return string
     */
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): ?string
    {
        return serialize([$this->siteId, $this->themeName, $this->mail, $this->domain]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        [$this->siteId, $this->themeName, $this->mail, $this->domain] = unserialize($serialized);
    }
}
