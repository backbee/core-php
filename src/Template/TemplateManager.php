<?php

namespace BackBee\Template;

/**
 * Class TemplateManager
 *
 * @package BackBee\Template
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TemplateManager
{
    /**
     * Get assets directory.
     *
     * @return string
     */
    public function getAssetsDirectory(): string
    {
        return __DIR__ . '/../../assets';
    }

    /**
     * Get css assets directory.
     *
     * @return string
     */
    public function getCssAssetsDirectory(): string
    {
        return $this->getAssetsDirectory() . '/css';
    }

    /**
     * Get js assets directory.
     *
     * @return string
     */
    public function getJsAssetsDirectory(): string
    {
        return $this->getAssetsDirectory() . '/js';
    }

    /**
     * Get fonts assets directory.
     *
     * @return string
     */
    public function getFontsAssetsDirectory(): string
    {
        return $this->getAssetsDirectory() . '/fonts';
    }
}
