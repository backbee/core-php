<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

use BackBee\ClassContent\Basic\Title;
use BackBee\NestedNode\Page;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class BlankType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return 'Blank layout';
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return 'blank';
    }

    /**
     * {@inheritdoc}
     */
    public function isDefault()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultContents()
    {
        return [
            Title::class => function(Title $title, Page $page) {
                $title->value = $page->getTitle();
            },
        ];
    }
}
