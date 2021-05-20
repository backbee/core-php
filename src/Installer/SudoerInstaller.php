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

use BackBee\Command\AbstractCommand;
use BackBee\Command\InstallCommand;
use BackBee\Security\User;
use App\StandaloneHelper;
use Exception;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class SudoerInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SudoerInstaller extends AbstractInstaller
{
    /**
     * Create sudoer.
     *
     * @param StyleInterface $io
     */
    public function createSudoer(StyleInterface $io): void
    {
        $io->section('Create sudoer');

        if (null !== $this->getEntityManager()->getRepository(User::class)->findOneBy([])) {
            $io->note('Super user already exists.');
            return;
        }

        $io->text('Admin user creation');
        $io->newLine();

        $email = $this->getEmail();
        $password = $this->getPassword();
        $encoder = $this->getApplication()->getSecurityContext()->getEncoderFactory()->getEncoder(User::class);

        $user = new User(
            $email,
            $encoder->encodePassword($password, ''),
            'SuperAdmin',
            'SuperAdmin'
        );

        try {
            $user
                ->setApiKeyEnabled(true)
                ->setActivated(true)
                ->setEmail($email)
                ->generateRandomApiKey()
            ;

            $this->getEntityManager()->persist($user);
            $this->getEntityManager()->flush();
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

        $config = AbstractCommand::parseYaml('security.yml', InstallCommand::CONFIG_REGULAR_YAML);
        $config['sudoers'][$user->getLogin()] = (int) $user->getId();

        file_put_contents(
            StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'security.yml',
            Yaml::dump($config, 3, 2)
        );

        $io->success(sprintf('Super admin "%s" has been created.', $email));
    }

    /**
     * Get email.
     *
     * @return string
     */
    private function getEmail(): string
    {
        if (null === ($email = AbstractCommand::getInput()->getOption('admin_username'))) {
            $email = AbstractCommand::askFor('Email (it will also be the username): ');
        }

        return $email;
    }

    /**
     * Get password.
     *
     * @return string
     */
    private function getPassword(): string
    {
        if (null === ($password = AbstractCommand::getInput()->getOption('admin_password'))) {
            $password = AbstractCommand::askFor('Password: ', true);
        }

        return $password;
    }
}