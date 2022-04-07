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

namespace BackBeeCloud\Design;

use BackBeeCloud\ThemeColor\ColorPanel;
use BackBeeCloud\ThemeColor\Color;
use BackBee\Bundle\Registry;
use BackBee\Bundle\Registry\Repository as RegistryRepository;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class GlobalContentManager
{
    const REGISTRY_SCOPE = 'CONFIG';
    const REGISTRY_TYPE = 'DESIGN';
    const REGISTRY_KEY = 'GLOBAL_CONTENT';

    /**
     * @var null|string
     */
    protected $headerBackgroundColor = Color::BACKGROUND_COLOR_ID;

    /**
     * @var bool
     */
    protected $hasHeaderMargin = false;

    /**
     * @var null|string
     */
    protected $footerBackgroundColor;

    /**
     * @var string
     */
    protected $copyrightBackgroundColor = Color::SECONDARY_COLOR_ID;

    /**
     * @var RegistryRepository
     */
    protected $registryRepository;

    /**
     * @var ColorPanel
     */
    protected $colorPanel;

    /**
     * @var array
     */
    protected $colors;

    public function __construct(RegistryRepository $registryRepository, ColorPanel $colorPanel)
    {
        $this->registryRepository = $registryRepository;
        $this->colorPanel = $colorPanel;
        $this->colors = array_map(function($color) {
            return $color->getId();
        }, $this->colorPanel->getAllColors());

        $this->restoreSettings();
    }

    public function getSettings()
    {
        return [
            'header_background_color' => $this->headerBackgroundColor,
            'has_header_margin' => $this->hasHeaderMargin,
            'footer_background_color' => $this->footerBackgroundColor,
            'copyright_background_color' => $this->copyrightBackgroundColor,
        ];
    }

    public function getHeaderBackgroundColor()
    {
        return $this->headerBackgroundColor;
    }

    public function getHasHeaderMargin()
    {
        return $this->hasHeaderMargin;
    }

    public function getFooterBackgroundColor()
    {
        return $this->footerBackgroundColor;
    }

    public function getCopyrightBackgroundColor()
    {
        $this->copyrightBackgroundColor;
    }

    public function updateHeaderBackgroundColor($headerBackgroundColor)
    {
        if (!in_array($headerBackgroundColor, $this->colors)) {
            throw new \InvalidArgumentException(sprintf(
                'Provided header background color: %s is not valid.',
                $headerBackgroundColor
            ));
        }

        $this->headerBackgroundColor = $headerBackgroundColor;

        $this->saveSettings();
    }

    public function updateHasHeaderMargin($hasHeaderMargin)
    {
        $this->hasHeaderMargin = (bool) $hasHeaderMargin;

        $this->saveSettings();
    }

    public function updateFooterBackgroundColor($footerBackgroundColor = null)
    {
        if (null !== $footerBackgroundColor) {
            if (!in_array($footerBackgroundColor, $this->colors)) {
                throw new \InvalidArgumentException(sprintf(
                    'Provided footer background color: %s is not valid.',
                    $footerBackgroundColor
                ));
            }
        }

        $this->footerBackgroundColor = $footerBackgroundColor;

        $this->saveSettings();
    }

    public function updateCopyrightBackgroundColor($copyrightBackgroundColor = null)
    {
        if (null !== $copyrightBackgroundColor) {
            if (!in_array($copyrightBackgroundColor, $this->colors)) {
                throw new \InvalidArgumentException(sprintf(
                    'Provided copyright background color: %s is not valid.',
                    $copyrightBackgroundColor
                ));
            }
        }

        $this->copyrightBackgroundColor = $copyrightBackgroundColor;

        $this->saveSettings();
    }

    protected function restoreSettings()
    {
        $parameters = [];

        if ($registry = $this->getRegistryEntity()) {
            $parameters = json_decode($registry->getValue(), true);
        }

        if (false == $parameters) {
            return;
        }

        //make compatible old sites with the header margin feature
        if (3 === count($parameters)) {
            list(
                $this->headerBackgroundColor,
                $this->footerBackgroundColor,
                $this->copyrightBackgroundColor,
            ) = $parameters;
        } else {
            list(
                $this->headerBackgroundColor,
                $this->hasHeaderMargin,
                $this->footerBackgroundColor,
                $this->copyrightBackgroundColor,
            ) = $parameters;
        }

        //disable transparency for header
        if (null === $this->headerBackgroundColor) {
            $this->headerBackgroundColor = $this->colorPanel->getBackgroundColor()->getId();
        }
    }

    protected function saveSettings()
    {
        $registry = $this->getRegistryEntity(true);
        $registry->setValue(json_encode([
            $this->headerBackgroundColor,
            $this->hasHeaderMargin,
            $this->footerBackgroundColor,
            $this->copyrightBackgroundColor,
        ]));

        $this->registryRepository->save($registry);
    }

    protected function getRegistryEntity($checkoutOnNull = false)
    {
        $registry = $this->registryRepository->findOneBy([
            'scope' => self::REGISTRY_SCOPE,
            'type' => self::REGISTRY_TYPE,
            'key' => self::REGISTRY_KEY,
        ]);

        if (null === $registry && true === $checkoutOnNull) {
            $registry = new Registry();
            $registry->setScope(self::REGISTRY_SCOPE);
            $registry->setType(self::REGISTRY_TYPE);
            $registry->setKey(self::REGISTRY_KEY);
        }

        return $registry;
    }
}
