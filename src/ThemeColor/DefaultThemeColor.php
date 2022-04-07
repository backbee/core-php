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
