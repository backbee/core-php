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

use BackBeeCloud\PageType\AbstractType;
use BackBee\ClassContent\Basic\PageByTagResult;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageByTagResultType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return 'Pages by tag result';
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return 'page_by_tag_result';
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDuplicable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isRemovable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isPullable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDumpable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultContents()
    {
        return [
            PageByTagResult::class => null,
        ];
    }
}

