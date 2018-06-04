<?php

namespace BackBeeCloud\Design;

use BackBeeCloud\Design\FontManager;
use BackBee\Bundle\Registry;
use BackBee\Bundle\Registry\Repository as RegistryRepository;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ButtonManager
{
    const SQUARE_SHAPE = 'square_shape';
    const SOFT_ROUNDED_SHAPE = 'soft_rounded_shape';
    const FULL_ROUNDED_SHAPE = 'full_rounded_shape';

    const REGISTRY_SCOPE = 'CONFIG';
    const REGISTRY_TYPE = 'DESIGN';
    const REGISTRY_KEY = 'BUTTON';

    /**
     * @var null|string
     */
    protected $font;

    /**
     * @var string
     */
    protected $shape = self::SOFT_ROUNDED_SHAPE;

    /**
     * @var RegistryRepository
     */
    protected $registryRepository;

    /**
     * @var FontManager
     */
    protected $fontManager;

    public function __construct(RegistryRepository $registryRepository, FontManager $fontManager)
    {
        $this->registryRepository = $registryRepository;
        $this->fontManager = $fontManager;

        $this->restoreSettings();
    }

    public function getSettings()
    {
        return [
            'font' => $this->font,
            'shape' => $this->shape,
        ];
    }

    public function getShapeValues()
    {
        return [
            self::SQUARE_SHAPE,
            self::SOFT_ROUNDED_SHAPE,
            self::FULL_ROUNDED_SHAPE,
        ];
    }

    public function getFont()
    {
        return $this->font;
    }

    public function updateFont($value = null)
    {
        if (null !== $value && !$this->fontManager->hasValue($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Provided button font value (:%s) is not valid.',
                $value
            ));
        }

        $this->font = $value;
        $this->saveSettings();
    }

    public function getShape()
    {
        return $this->shape;
    }

    public function updateShape($value)
    {
        if (!in_array($value, [self::SQUARE_SHAPE, self::SOFT_ROUNDED_SHAPE, self::FULL_ROUNDED_SHAPE])) {
            throw new \InvalidArgumentException(sprintf(
                'Provided button shape value (:%s) is not valid.',
                $value
            ));
        }

        $this->shape = $value;
        $this->saveSettings();
    }

    protected function restoreSettings()
    {
        if ($registry = $this->getRegistryEntity()) {
            list(
                $this->font,
                $this->shape
            ) = json_decode($registry->getValue(), true);
        }
    }

    protected function saveSettings()
    {
        $registry = $this->getRegistryEntity(true);
        $registry->setValue(json_encode([
            $this->font,
            $this->shape,
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
