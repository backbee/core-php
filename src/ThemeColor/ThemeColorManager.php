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

namespace BackBeeCloud\ThemeColor;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ThemeColorManager
{
    /**
     * @var array
     */
    protected $themes = [];

    /**
     * @var ThemeColorInterface
     */
    protected $defaultTheme;

    public function __construct(array $themes)
    {
        foreach ($themes as $theme) {
            $this->add($theme);
        }

        $this->defaultTheme = array_values($this->themes)[0];
    }

    public function getDefault()
    {
        return $this->defaultTheme;
    }

    public function all()
    {
        return array_values($this->themes);
    }

    public function add(ThemeColorInterface $theme)
    {
        $this->themes[$theme->getUniqueName()] = $theme;
    }

    public function getByUniqueName($uniqueName)
    {
        if (!isset($this->themes[$uniqueName])) {
            throw new \InvalidArgumentException(sprintf('Theme does not exist: %s.', $uniqueName));
        }

        return $this->themes[$uniqueName];
    }

    public function getByColorPanel(ColorPanel $colorPanel)
    {
        $result = null;
        foreach ($this->themes as $theme) {
            if (
                $theme->getColorPanel()->getSecondaryColor()->isEqualTo($colorPanel->getSecondaryColor())
                && $theme->getColorPanel()->getTextColor()->isEqualTo($colorPanel->getTextColor())
                && $theme->getColorPanel()->getBackgroundColor()->isEqualTo($colorPanel->getBackgroundColor())
            ) {
                $result = $theme;

                break;
            }
        }

        return $result ?: $this->getDefault();
    }
}
