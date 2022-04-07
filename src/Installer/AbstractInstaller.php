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

namespace BackBee\Installer;

use BackBee\ApplicationInterface;
use BackBee\BBApplication;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class AbstractInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class AbstractInstaller
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ApplicationInterface
     */
    private $application;

    /**
     * AbstractInstaller constructor.
     *
     * @param BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        $this->entityManager = $application->getEntityManager();
        $this->application = $application;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @return ApplicationInterface
     */
    public function getApplication(): ApplicationInterface
    {
        return $this->application;
    }
}