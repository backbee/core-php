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
abstract class AbstractType implements TypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function layoutName()
    {
        return 1 === preg_match('~([a-z]+)$~i', static::class, $matches)
            ? str_replace('Type', 'Layout', $matches[1]) . '.twig'
            : ''
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDefault()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDuplicable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRemovable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isPullable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDumpable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultContents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function exclusiveClassContents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'unique_name'   => $this->uniqueName(),
            'label'         => $this->label(),
            'is_default'    => $this->isDefault(),
            'is_protected'  => $this->isProtected(),
            'is_removable'  => $this->isRemovable(),
            'is_duplicable' => $this->isDuplicable(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($raw)
    {
    }
}
