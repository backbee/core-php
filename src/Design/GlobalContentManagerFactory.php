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
