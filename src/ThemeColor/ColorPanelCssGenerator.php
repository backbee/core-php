<?php

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
                'color_darken10' => ColorPanelUtils::darken($color->getColor(), 10),
                'color_darken15' => ColorPanelUtils::darken($color->getColor(), 15),
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
