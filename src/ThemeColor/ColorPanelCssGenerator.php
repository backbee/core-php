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

use BackBee\Renderer\Renderer;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorPanelCssGenerator
{
    /**
     * @var ColorPanel
     */
    protected $colorPanel;

    /**
     * {@inheritdoc}
     */
    protected $renderer;

    public function __construct(ColorPanelManager $colorPanelManager, Renderer $renderer)
    {
        $this->colorPanel = $colorPanelManager->getColorPanel();
        $this->renderer = $renderer;
    }

    public function getCurrentHash()
    {
        return md5(json_encode($this->colorPanel));
    }

    public function generate()
    {
        $darkerColor = ColorPanelUtils::findDarkerColor([
            $this->colorPanel->getBackgroundColor()->getColor(),
            $this->colorPanel->getTextColor()->getColor(),
        ]);

        $colors = [];
        foreach ($this->colorPanel->getAllColors() as $color) {
            $colors[] = [
                'id' => $color->getId(),
                'color' => $color->getColor(),
                'text_color' => ColorPanelUtils::getTextColorByBackgroundColor(
                    $color->getColor(),
                    $darkerColor
                ),
                'color_darken10' => ColorPanelUtils::colorShade($color->getColor(), -10),
                'color_darken15' => ColorPanelUtils::colorShade($color->getColor(), -15),
                'color_opacity60' => ColorPanelUtils::addOpacity($color->getColor(), 0.6),
            ];
        }

        return str_replace(
            [' {', ';}', ': '],
            ['{', '}', ':'],
            preg_replace(
                ["~[\s]{2,}~", "~\n~"],
                '',
                $this->renderer->partial('theme_color/color_panel.css.twig', [
                    'colors' => $colors,
                    'primary_color' => $this->colorPanel->getPrimaryColor()->getColor(),
                    'primary_text_color' => ColorPanelUtils::getTextColorByBackgroundColor(
                        $this->colorPanel->getPrimaryColor()->getColor(),
                        $darkerColor
                    ),
                    'secondary_color' => $this->colorPanel->getSecondaryColor()->getColor(),
                    'background_color' => $this->colorPanel->getBackgroundColor()->getColor(),
                    'text_color' => $this->colorPanel->getTextColor()->getColor(),
                ])
            )
        );
    }
}
