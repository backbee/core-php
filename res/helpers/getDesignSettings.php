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

namespace BackBee\Renderer\Helper;

use BackBeeCloud\Design\ButtonManager;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Renderer\Helper\AbstractHelper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getDesignSettings extends AbstractHelper
{
    const BUTTON_SQUARE_SHAPE_VALUE = 'square';
    const BUTTON_SOFT_ROUNDED_SHAPE_VALUE = 'radius';
    const BUTTON_FULL_ROUNDED_SHAPE_VALUE = 'rounded';
    const BUTTON_DEFAULT_SHAPE_VALUE = self::BUTTON_SOFT_ROUNDED_SHAPE_VALUE;

    /**
     * @var array
     */
    protected $settings;

    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $container = $renderer->getApplication()->getContainer();

        $this->settings = [
            'button' => $this->computeButtonSettings($container->get('cloud.design.button.manager')->getSettings()),
            'global_content' => $this->computeButtonSettings($container->get('cloud.design.global.content.manager')->getSettings()),
        ];
    }

    public function __invoke($type)
    {
        return isset($this->settings[$type]) ? $this->settings[$type] : null;
    }

    protected function computeButtonSettings(array $settings)
    {
        $settings['shape'] = isset($settings['shape'])
            ? $settings['shape']
            : self::BUTTON_DEFAULT_SHAPE_VALUE
        ;

        switch ($settings['shape']) {
            case ButtonManager::SQUARE_SHAPE:
                $settings['shape'] = self::BUTTON_SQUARE_SHAPE_VALUE;

                break;
            case ButtonManager::FULL_ROUNDED_SHAPE:
                $settings['shape'] = self::BUTTON_FULL_ROUNDED_SHAPE_VALUE;

                break;
            default:
                $settings['shape'] = self::BUTTON_DEFAULT_SHAPE_VALUE;
        }

        return $settings;
    }
}



