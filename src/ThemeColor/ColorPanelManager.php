<?php

namespace BackBeeCloud\ThemeColor;

use BackBee\Bundle\Registry;
use BackBee\Bundle\Registry\Repository as RegistryRepository;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorPanelManager
{
    const REGISTRY_SCOPE = 'CONFIG';
    const REGISTRY_TYPE = 'THEME_COLOR';
    const REGISTRY_KEY = 'COLOR_PANEL';

    /**
     * @var RegistryRepository
     */
    protected $registryRepository;

    /**
     * @var ThemeColorManager
     */
    protected $themeColorManager;

    /**
     * @var ColorPanel
     */
    protected $colorPanel;

    public function __construct(RegistryRepository $registryRepository, ThemeColorManager $themeColorManager)
    {
        $this->registryRepository = $registryRepository;
        $this->themeColorManager = $themeColorManager;

        $this->restore();
        if (null === $this->colorPanel) {
            $this->colorPanel = $this->themeColorManager->getDefault()->getColorPanel();
        }
    }

    public function getColorPanel()
    {
        return $this->colorPanel;
    }

    public function updateColorPanel(array $data)
    {
        if (!$this->isValidUpdateData($data)) {
            throw new \InvalidArgumentException('Invalid update data.');
        }

        $this->colorPanel->setPrimaryColor(Color::createPrimaryColor($data['primary']));

        $customColors = [];
        $customColorIds = array_map(function (Color $color) {
            return $color->getId();
        }, $this->colorPanel->getCustomColors());

        foreach ($data['custom_colors'] as $content) {
            if (isset($content['id'])) {
                if (!in_array($content['id'], $customColorIds)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Custom color id does not exist: %s. Update aborted.',
                        $content['id']
                    ));
                }
            }

            $customColors[] = Color::createColor($content['color'], $content['id']);
        }

        $this->colorPanel->setCustomColors($customColors);

        $this->save();
    }

    public function changeThemeColor($themeUniqueName, $conservePrimaryColor = true)
    {
        $customPrimaryColor = $this->colorPanel->getPrimaryColor();
        $customColors = $this->colorPanel->getCustomColors();

        $this->colorPanel = $this->themeColorManager->getByUniqueName($themeUniqueName)->getColorPanel();
        $this->colorPanel->setCustomColors($customColors);

        if ($conservePrimaryColor) {
            $this->colorPanel->setPrimaryColor($customPrimaryColor);
        }

        $this->save();
    }

    protected function isValidUpdateData(array $data)
    {
        if (!isset($data['primary']) || !is_string($data['primary'])) {
            return false;
        }

        if (!isset($data['custom_colors'])) {
            return false;
        }

        foreach ($data['custom_colors'] as $value) {
            if (!isset($value['color']) || !is_string($value['color'])) {
                return false;
            }
        }

        return true;
    }

    protected function restore()
    {
        if (null !== $registry = $this->getRegistryEntity()) {
            $this->colorPanel = ColorPanel::restore(json_decode($registry->getValue(), true));
        }
    }

    protected function save()
    {
        if (null === $registry = $this->getRegistryEntity()) {
            $registry = new Registry();
            $registry->setScope(self::REGISTRY_SCOPE);
            $registry->setType(self::REGISTRY_TYPE);
            $registry->setKey(self::REGISTRY_KEY);
        }

        $registry->setValue(json_encode($this->colorPanel->dump()));
        $this->registryRepository->save($registry);
    }

    protected function getRegistryEntity()
    {
        return $this->registryRepository->findOneBy([
            'scope' => self::REGISTRY_SCOPE,
            'type' => self::REGISTRY_TYPE,
            'key' => self::REGISTRY_KEY,
        ]);
    }
}
