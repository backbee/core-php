<?php

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

    public static function darken($colorHexCode, $percentage)
    {
        $percentage = abs($percentage)/100;
        $rgb = ColorRGB::fromHex($colorHexCode);

        $newColorComponents = [
            min( max(0, $rgb->getRed() - $rgb->getRed() * $percentage), 255),
            min( max(0, $rgb->getGreen() - $rgb->getGreen() * $percentage), 255),
            min( max(0, $rgb->getBlue() - $rgb->getBlue() * $percentage), 255)
        ];

        $result = '';
        foreach ($newColorComponents as $component) {
            $result = $result . str_pad(dechex($component), 2, 0);
        }

        return '#' . $result;
    }

    public static function addOpacity($colorHexCode, $opacity)
    {
        $rgb = ColorRGB::fromHex($colorHexCode);

        return 'rgba(' . $rgb->getRed() . ',' . $rgb->getGreen() . ',' . $rgb->getBlue() . ',' . $opacity . ')';
    }
}
