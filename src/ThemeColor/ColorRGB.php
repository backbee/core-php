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

namespace BackBeeCloud\ThemeColor;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorRGB
{
    /**
     * @var string
     */
    protected $red;

    /**
     * @var string
     */
    protected $green;

    /**
     * @var string
     */
    protected $blue;

    public static function fromHex($color)
    {
        if (1 !== preg_match('/^#([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})$/i', $color, $matches)) {
            throw new \InvalidArgumentException(sprintf(
                'invalid color hexcode: %s.',
                $color
            ));
        }

        array_shift($matches);
        list($red, $green, $blue) = $matches;

        return new self(hexdec($red), hexdec($green), hexdec($blue));
    }

    public function __construct($red, $green, $blue)
    {
        $this->red = $red;
        $this->green = $green;
        $this->blue = $blue;
    }

    public function getRed()
    {
        return $this->red;
    }

    public function getGreen()
    {
        return $this->green;
    }

    public function getBlue()
    {
        return $this->blue;
    }
}
