<?php

namespace BackBeeCloud\ThemeColor;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class Color implements \JsonSerializable
{
    const PRIMARY_COLOR_ID = 'color-primary';
    const SECONDARY_COLOR_ID = 'color-secondary';
    const TEXT_COLOR_ID = 'color-text';
    const BACKGROUND_COLOR_ID = 'color-background';

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $color;

    public static function isValidColor($hexCode)
    {
        return 1 === preg_match('/^#[a-f0-9]{6}$/i', $hexCode);
    }

    public static function createColor($colorHexCode, $id = null)
    {
        $id = (string) $id;
        $id = $id ?: self::generateUniqueId();

        return new self($id, $colorHexCode);
    }

    public static function createPrimaryColor($colorHexCode)
    {
        return new self(self::PRIMARY_COLOR_ID, $colorHexCode);
    }

    public static function createSecondaryColor($colorHexCode)
    {
        return new self(self::SECONDARY_COLOR_ID, $colorHexCode);
    }

    public static function createTextColor($colorHexCode)
    {
        return new self(self::TEXT_COLOR_ID, $colorHexCode);
    }

    public static function createBackgroundColor($colorHexCode)
    {
        return new self(self::BACKGROUND_COLOR_ID, $colorHexCode);
    }

    private function __construct($id, $colorHexCode)
    {
        if (!self::isValidColor($colorHexCode)) {
            throw new \InvalidArgumentException(sprintf(
                'invalid color hexcode: %s.',
                $colorHexCode
            ));
        }

        $this->id = $id;
        $this->color = $colorHexCode;
    }

    public function getId()
    {
        return $this->id;
    }

    protected static function generateUniqueId()
    {
        usleep(100);

        $uniqueId = str_replace('.', '', (string) microtime(true));
        $uniqueId = str_pad($uniqueId, 14, '0');

        return 'color-' . $uniqueId;
    }

    public function getColor()
    {
        return $this->color;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'color' => $this->color,
        ];
    }
}
