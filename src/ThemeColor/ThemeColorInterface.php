<?php

namespace BackBeeCloud\ThemeColor;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
interface ThemeColorInterface extends \JsonSerializable
{
    /**
     * Returns theme unique name.
     *
     * @return string
     */
    public function getUniqueName();

    /**
     * Returns theme label.
     *
     * @return string
     */
    public function getLabel();

    /**
     * Returns the ColorPanel associated to a theme
     *
     * @return ColorPanel
     */
    public function getColorPanel();
}
