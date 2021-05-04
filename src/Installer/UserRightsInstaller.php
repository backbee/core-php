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

namespace BackBee\Installer;

use Exception;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use function is_array;
use function is_string;

/**
 * Class UserRightsInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class UserRightsInstaller extends AbstractInstaller
{
    /**
     * Install.
     *
     * @param SymfonyStyle $io
     */
    public function install(SymfonyStyle $io): void
    {
        $io->section('Install user rights');

        try {
            $securityConfig = $this->getApplication()->getConfig()->getSecurityConfig();
        } catch (Exception $exception) {
            $io->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        if (!isset($securityConfig['group_types']) || !is_array($securityConfig['group_types'])) {
            throw new RuntimeException('"group_types" configuration is missing from security.yml.');
        }

        if (!isset($securityConfig['default_group_type']) || !is_string($securityConfig['default_group_type'])) {
            throw new RuntimeException('"default_group_type" configuration is missing from security.yml.');
        }

        $installer = $this->getApplication()->getContainer()->get('core.user_right.installer');

        if ($installer->isInstalled()) {
            $installer->syncGroupTypes($securityConfig['group_types']);
            $io->success('User right successfully updated.');
            return;
        }

        $installer->install($securityConfig['group_types'], $securityConfig['default_group_type']);

        $io->success('User right successfully installed.');
    }
}