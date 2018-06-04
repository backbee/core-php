<?php

namespace BackBeeCloud\Design;

use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ButtonManagerFactory
{
    public static function createButtonManager(EntityManager $entityManager, FontManager $fontManager)
    {
        $registryRepository = $entityManager->getRepository(Registry::class);

        return new ButtonManager($registryRepository, $fontManager);
    }
}
