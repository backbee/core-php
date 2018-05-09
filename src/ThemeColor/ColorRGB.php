<?php

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
