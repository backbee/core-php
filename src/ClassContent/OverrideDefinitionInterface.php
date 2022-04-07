<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

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
