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

namespace BackBeeCloud\PageType;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TemplateCustomType extends AbstractType
{
    /**
     * @var string
     */
    private $uniqueName;

    /**
     * @var string
     */
    private $label;

    /**
     * @var array
     */
    private $contentsRawData;

    public function __construct($uniqueName, $label, array $contentsRawData = [])
    {
        $this->uniqueName = $uniqueName;
        $this->label = $label;
        $this->contentsRawData = $contentsRawData;
    }

    /**
     * {@inheritdoc}
     */
    public function layoutName()
    {
        return (new BlankType())->layoutName();
    }

    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return $this->label;
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return $this->uniqueName;
    }

    /**
     * Returns an array that contains contents raw data.
     *
     * @return array
     */
    public function contentsRawData()
    {
        return $this->contentsRawData;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            $this->uniqueName,
            $this->label,
            $this->contentsRawData,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($raw)
    {
        list(
            $this->uniqueName,
            $this->label,
            $this->contentsRawData
        ) = unserialize($raw);
    }
}
