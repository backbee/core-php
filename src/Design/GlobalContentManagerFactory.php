<?php

namespace BackBeeCloud\Design;

use BackBeeCloud\ThemeColor\ColorPanelManager;
use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class GlobalContentManagerFactory
{
    public static function createGlobalContentManager(EntityManager $entityManager, ColorPanelManager $colorPanelManager)
    {
        $registryRepository = $entityManager->getRepository(Registry::class);

        return new GlobalContentManager($registryRepository, $colorPanelManager->getColorPanel());
    }
}
