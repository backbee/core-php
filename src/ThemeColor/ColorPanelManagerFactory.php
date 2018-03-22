<?php

namespace BackBeeCloud\ThemeColor;

use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorPanelManagerFactory
{
    public static function createColorPanelManager(EntityManager $entityManager, ThemeColorManager $themeColorManager)
    {
        $registryRepository = $entityManager->getRepository(Registry::class);

        return new ColorPanelManager($registryRepository, $themeColorManager);
    }
}
