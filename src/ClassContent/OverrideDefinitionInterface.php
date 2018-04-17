<?php

namespace BackBeeCloud\ClassContent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface OverrideDefinitionInterface
{
    /**
     * {@see \BackBee\ClassContent\AbstractContent::getContentType()}
     *
     * Returns the content type of the classcontent to override.
     *
     * @return string
     */
    public function getContentType();

    /**
     * Returns source name from where the original classcontent to override belongs.
     * It is **HIGHLY RECOMMENDED** to return the composer package name (example: backbee-planet/core-php).
     *
     * @return string
     */
    public function getSourceName();

    /**
     * Returns an array of tranformations to apply to the original classcontent yaml.
     *
     * @return ClassContentTransformationInterface[]
     */
    public function getTransformations();
}
