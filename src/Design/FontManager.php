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

namespace BackBeeCloud\Design;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class FontManager
{
    /**
     * @var array
     */
    protected $fonts;

    public function __construct()
    {
        $this->fonts = [
            [
                'label' => 'Arial',
                'value' => 'Arial, sans-serif',
            ],
            [
                'label' => 'Georgia',
                'value' => 'Georgia, serif',
            ],
            [
                'label' => 'Helvetica',
                'value' => 'Helvetica, sans-serif',
            ],
            [
                'label' => 'Time New Roman',
                'value' => 'Time New Roman, serif',
            ],
            [
                'label' => 'Trebuchet MS',
                'value' => 'Trebuchet MS, sans-serif',
            ],
            [
                'label' => 'Verdana',
                'value' => 'Verdana, sans-serif',
            ],
        ];
    }

    public function all()
    {
        return $this->fonts;
    }

    public function hasValue($value)
    {
        return in_array($value, array_column($this->fonts, 'value'));
    }
}
