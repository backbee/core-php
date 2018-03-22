<?php

namespace BackBeeCloud\ThemeColor;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class DefaultThemeColor implements ThemeColorInterface
{
    /**
     * {@inheritdoc}
     */
    public function getUniqueName()
    {
        return 'default';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Default theme';
    }

    /**
     * {@inheritdoc}
     */
    public function getColorPanel()
    {
        return new ColorPanel(
            Color::createPrimaryColor('#39829d'),
            Color::createSecondaryColor('#e0e1e6'),
            Color::createTextColor('#515256'),
            Color::createBackgroundColor('#ffffff')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'label' => $this->getLabel(),
            'unique_name' => $this->getUniqueName(),
            'color_panel' => $this->getColorPanel(),
        ];
    }
}
