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

namespace BackBee\Command;

use BackBee\Installer\DatabaseInstaller;
use BackBee\Installer\RepositoryInstaller;
use BackBeePlanet\Standalone\Application;
use BackBeePlanet\Standalone\StandaloneHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class InstallCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class InstallCommand extends AbstractCommand
{
    public const CONFIG_DIST_YAML = [StandaloneHelper::class, 'distDir'];
    public const CONFIG_REGULAR_YAML = [StandaloneHelper::class, 'configDir'];

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var string
     */
    protected $appName;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:install')
            ->setDescription('Installs everything to get BackBee Standalone ready for use.')
            ->addOption('app_name', null, InputOption::VALUE_OPTIONAL, 'The app name to set.')
            ->addOption('server_name', null, InputOption::VALUE_OPTIONAL, 'The server name to set.')
            ->addOption('db_host', null, InputOption::VALUE_OPTIONAL, 'The database host to set.')
            ->addOption('db_name', null, InputOption::VALUE_OPTIONAL, 'The database name to set.')
            ->addOption('db_username', null, InputOption::VALUE_OPTIONAL, 'The database username to set.')
            ->addOption('db_password', null, InputOption::VALUE_OPTIONAL, 'The database password to set.')
            ->addOption('elasticsearch_host', null, InputOption::VALUE_OPTIONAL, 'The elastic search host to set.')
            ->addOption('redis_host', null, InputOption::VALUE_OPTIONAL, 'The redis host to set.')
            ->addOption('mailer_host', null, InputOption::VALUE_OPTIONAL, 'The mailer host to set.')
            ->addOption('mailer_port', null, InputOption::VALUE_OPTIONAL, 'The mailer port to set.')
            ->addOption('mailer_from', null, InputOption::VALUE_OPTIONAL, 'The mailer from to set.')
            ->addOption('mailer_username', null, InputOption::VALUE_OPTIONAL, 'The mailer username from to set.')
            ->addOption('mailer_password', null, InputOption::VALUE_OPTIONAL, 'The mailer password from to set.')
            ->addOption('mailer_encryption', null, InputOption::VALUE_OPTIONAL, 'The mailer encryption from to set.')
            ->addOption('admin_username', null, InputOption::VALUE_OPTIONAL, 'The admin username to set.')
            ->addOption('admin_password', null, InputOption::VALUE_OPTIONAL, 'The admin password to set.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        self::setCommand($this);
        self::setInput($input);
        self::setOutput($output);

        $io = new SymfonyStyle($input, $output);
        $io->section('BackBee Standalone installer is now processing');

        RepositoryInstaller::buildRepository($io);
        DatabaseInstaller::createDatabase($io);

        Application::setRepositoryDir(StandaloneHelper::repositoryDir());
        $app = new Application();

        $app->getContainer()->get('core.installer.database')->updateDatabaseSchema($io);
        $app->getContainer()->get('core.installer.sudoer')->createSudoer($io);
        $app->getContainer()->get('core.installer.site')->createSite($io);
        $app->getContainer()->get('core.installer.layout')->createCleanLayout($io);
        $app->getContainer()->get('core.installer.page')->createRootPage($io);
        $app->getContainer()->get('core.installer.keyword')->createRootKeyword($io);
        $app->getContainer()->get('core.installer.user_rights')->install($io);
        $app->getContainer()->get('core.installer.elasticsearch')->index($io);
        $app->getContainer()->get('core.installer.assets')->install($io);

        $this->cleanup();

        $io->success('Installation of BackBee Standalone is now done.');

        return 0;
    }
}
