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
class ColorPanelUtils
{
    const DEFAULT_LIGHT_COLOR = '#ffffff';

    public static function findDarkerColor(array $colors)
    {
        $darkest = reset($colors);
        $referrenceRGB = ColorRGB::fromHex($darkest);
        $referrenceBrightness = self::getColorBrightness($referrenceRGB);

        foreach ($colors as $color) {
            $colorRGB = ColorRGB::fromHex($color);
            $colorBrightness = self::getColorBrightness($colorRGB);

            if ($referrenceBrightness > $colorBrightness) {
                $referrenceBrightness = $colorBrightness;
                $darkest = $color;
            }
        }

        return $darkest;
    }

    public static function getTextColorByBackgroundColor($bgColor, $darkColor, $lightColor = self::DEFAULT_LIGHT_COLOR)
    {
        $rbgBgColor = ColorRGB::fromHex($bgColor);
        $backgroundBrightness = self::getColorBrightness($rbgBgColor);

        if ($backgroundBrightness < 125) {
            return $lightColor;
        }

        return $darkColor;
    }

    public static function getColorBrightness(ColorRGB $color)
    {
        return ($color->getRed() * 299 + $color->getGreen() * 587 + $color->getBlue() * 114) / 1000;
    }

    public static function addOpacity($colorHexCode, $opacity)
    {
        $rgb = ColorRGB::fromHex($colorHexCode);

        return 'rgba(' . $rgb->getRed() . ',' . $rgb->getGreen() . ',' . $rgb->getBlue() . ',' . $opacity . ')';
    }

    public static function colorShade($colorHexCode, $percentage)
    {
        $hsl = self::rgbToHSL($colorHexCode);

        if ($hsl['l'] < 0.05) {
            $percentage = abs($percentage);
        } elseif ($hsl['l'] > 0.95) {
            $percentage = (-1) * abs($percentage);
        }

        $hsl['l'] += $percentage/100;
        $rgb = self::hslToRGB($hsl['h'], $hsl['s'], $hsl['l']);

        $result = '';
        foreach ($rgb as $component) {
            $result = $result . str_pad(dechex($component), 2, 0, STR_PAD_LEFT);
        }

        return '#' . $result;
    }

    protected static function rgbToHSL($colorHexCode)
    {
        $rgb = ColorRGB::fromHex($colorHexCode);

        $rPercentage = $rgb->getRed() / 255;
        $gPercentage = $rgb->getGreen() / 255;
        $bPercentage = $rgb->getBlue() / 255;

        $max = max($rPercentage, $gPercentage, $bPercentage);
        $min = min($rPercentage, $gPercentage, $bPercentage);

        $hue;
        $saturation;
        $luminosity = ($max + $min) / 2;
        $diff = $max - $min;

        if ($diff == 0) {
            $hue = 0;
            $saturation = 0;
        } else {
            $saturation = $diff / (1 - abs(2 * $luminosity -1));

            switch($max) {
                case $rPercentage:
                    $hue = 60 * fmod((($gPercentage - $bPercentage) / $diff), 6);
                    if ($bPercentage > $gPercentage) {
                        $hue += 360;
                    }
                    break;

                case $gPercentage:
                    $hue = 60 * (($bPercentage - $rPercentage)/ $diff + 2);
                    break;

                case $bPercentage:
                    $hue = 60 * (($rPercentage - $gPercentage)/ $diff + 4);
                    break;
            }
        }

        return array('h' => round($hue, 2), 's' => round($saturation, 2), 'l' => round($luminosity, 2));
    }

    protected static function hslToRGB($hue, $saturation, $luminosity)
    {
        $chroma = (1 - abs(2 * $luminosity - 1)) * $saturation;
        $value = $chroma * (1 - abs(fmod(($hue / 60), 2) - 1));
        $mixing = $luminosity - ($chroma / 2);

        switch ($hue) {
            case ($hue < 60):
                $red = $chroma;
                $green = $value;
                $blue = 0;
                break;

            case ($hue < 120):
                $red = $value;
                $green = $chroma;
                $blue = 0;
                break;

            case ($hue < 180):
                $red = 0;
                $green = $chroma;
                $blue = $value;
                break;

            case ($hue < 240):
                $red = 0;
                $green = $value;
                $blue = $chroma;
                break;

            case ($hue < 300):
                $red = $value;
                $green = 0;
                $blue = $chroma;
                break;

            default:
                $red = $chroma;
                $green = 0;
                $blue = $value;
                break;
        }

        $red = floor(($red + $mixing) * 255);
        $green = floor(($green + $mixing) * 255);
        $blue = floor(($blue + $mixing) * 255);

        $newColorComponent = [
            $red,
            $green,
            $blue
        ];

        return $newColorComponent;
    }
}
