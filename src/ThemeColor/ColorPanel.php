<?php

namespace BackBeeCloud\ThemeColor;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorPanel implements \JsonSerializable
{
    /**
     * @var Color
     */
    protected $primaryColor;

    /**
     * @var Color
     */
    protected $secondaryColor;

    /**
     * @var Color
     */
    protected $textColor;

    /**
     * @var Color
     */
    protected $backgroundColor;

    /**
     * @var Color[]
     */
    protected $customColors = [];

    public function __construct(Color $primaryColor, Color $secondaryColor, Color $textColor, Color $backgroundColor)
    {
        if (Color::SECONDARY_COLOR_ID !== $secondaryColor->getId()) {
            throw new \InvalidArgumentException(sprintf(
                'Provided color (%s %s) is not a valid secondary color.',
                $secondaryColor->getId(),
                $secondaryColor->getColor()
            ));
        }

        if (Color::TEXT_COLOR_ID !== $textColor->getId()) {
            throw new \InvalidArgumentException(sprintf(
                'Provided color (%s %s) is not a valid text color.',
                $textColor->getId(),
                $textColor->getColor()
            ));
        }

        if (Color::BACKGROUND_COLOR_ID !== $backgroundColor->getId()) {
            throw new \InvalidArgumentException(sprintf(
                'Provided color (%s %s) is not a valid background color.',
                $backgroundColor->getId(),
                $backgroundColor->getColor()
            ));
        }

        $this->setPrimaryColor($primaryColor);
        $this->secondaryColor = $secondaryColor;
        $this->textColor = $textColor;
        $this->backgroundColor = $backgroundColor;
    }

    public function getAllColors()
    {
        return array_merge([
            $this->primaryColor,
            $this->secondaryColor,
            $this->textColor,
            $this->backgroundColor,
        ], array_values($this->customColors));
    }

    public function getPrimaryColor()
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(Color $color)
    {
        if (Color::PRIMARY_COLOR_ID !== $color->getId()) {
            throw new \InvalidArgumentException(sprintf(
                'Provided color (%s %s) is not primary.',
                $color->getId(),
                $color->getColor()
            ));
        }

        $this->primaryColor = $color;
    }

    public function getSecondaryColor()
    {
        return $this->secondaryColor;
    }

    public function getTextColor()
    {
        return $this->textColor;
    }

    public function getBackgroundColor()
    {
        return $this->backgroundColor;
    }

    public function getCustomColors()
    {
        return array_values($this->customColors);
    }

    public function addCustomColor(Color $color)
    {
        if (isset($this->customColors[$color->getColor()])) {
            throw new \InvalidArgumentException(sprintf(
                'color exists already %s.',
                $color->getColor()
            ));
        }

        $this->customColors[$color->getColor()] = $color;
    }

    public function setCustomColors(array $customColors)
    {
        $this->customColors = [];
        foreach ($customColors as $color) {
            $this->addCustomColor($color);
        }
    }

    public function deleteCustomColor(Color $color)
    {
        unset($this->customColors[$color->getColor()]);
    }

    public static function restore(array $data)
    {
        if (!self::isValidRestoreData($data)) {
            throw new \InvalidArgumentException('Invalid restore data.');
        }

        try {
            $colorPanel = new self(
                Color::createPrimaryColor($data['primary']['color']),
                Color::createSecondaryColor($data['secondary']['color']),
                Color::createTextColor($data['textColor']['color']),
                Color::createBackgroundColor($data['backgroundColor']['color'])
            );

            foreach ($data['customColors'] as $color) {
                $colorPanel->addCustomColor(Color::createColor($color['color'], $color['id']));
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException('Invalid restore data: ' . $exception->getMessage());
        }

        return $colorPanel;
    }

    public function dump()
    {
        return json_decode(json_encode($this), true);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'primary' => $this->primaryColor,
            'secondary' => $this->secondaryColor,
            'textColor' => $this->textColor,
            'backgroundColor' => $this->backgroundColor,
            'customColors' => $this->getCustomColors(),
        ];
    }

    protected static function isValidRestoreData(array $data)
    {
        if (5 !== count($data)) {
            return false;
        }

        if (
            !isset($data['primary'])
            || !is_array($data['primary'])
            || !isset($data['secondary'])
            || !is_array($data['secondary'])
            || !isset($data['textColor'])
            || !is_array($data['textColor'])
            || !isset($data['backgroundColor'])
            || !is_array($data['backgroundColor'])
            || !isset($data['customColors'])
            || !is_array($data['customColors'])
        ) {
            return false;
        }

        foreach ($data as $color) {
            foreach ($color as $value) {
                if (!is_array($value) && !is_string($value)) {
                    return false;
                }

                if (
                    is_array($value)
                    && (
                        !isset($value['color'])
                        || !is_string($value['color'])
                        || !isset($value['id'])
                        || !is_string($value['id'])
                    )
                ) {
                    return false;
                }
            }
        }

        return true;
    }
}
