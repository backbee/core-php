<?php

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
    protected $headerBackgroundColor;

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
            'footer_background_color' => $this->footerBackgroundColor,
            'copyright_background_color' => $this->copyrightBackgroundColor,
        ];
    }

    public function getHeaderBackgroundColor()
    {
        return $this->headerBackgroundColor;
    }

    public function getFooterBackgroundColor()
    {
        return $this->footerBackgroundColor;
    }

    public function getCopyrightBackgroundColor()
    {
        $this->copyrightBackgroundColor;
    }

    public function updateHeaderBackgroundColor($headerBackgroundColor = null)
    {
        if ($headerBackgroundColor !== null) {
            if (!in_array($headerBackgroundColor, $this->colors)) {
                throw new \InvalidArgumentException(sprintf(
                    'Provided header background color: %s is not valid.',
                    $headerBackgroundColor
                ));
            }
        }

        $this->headerBackgroundColor = $headerBackgroundColor;

        $this->saveSettings();
    }

    public function updateFooterBackgroundColor($footerBackgroundColor = null)
    {
        if ($footerBackgroundColor !== null) {
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
        if ($copyrightBackgroundColor !== null) {
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
        if ($registry = $this->getRegistryEntity()) {
            list(
                $this->headerBackgroundColor,
                $this->footerBackgroundColor,
                $this->copyrightBackgroundColor,
            ) = json_decode($registry->getValue(), true);
        }
    }

    protected function saveSettings()
    {
        $registry = $this->getRegistryEntity(true);
        $registry->setValue(json_encode([
            $this->headerBackgroundColor,
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
