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

namespace BackBeePlanet\Standalone;

use Webmozart\Console\Config\DefaultApplicationConfig;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CommandLineConfig extends DefaultApplicationConfig
{
    /**
     * @var string
     */
    protected $rootDir;

    public function __construct($rootDir)
    {
        if (!class_exists(Application::class)) {
            echo sprintf(
                "\033[1;31mFailed to start command line. %s class must exist and extend %s\033[0m\n",
                Application::class,
                AbstractApplication::class
            );
            exit(1);
        }

        parent::__construct();

        $this->rootDir = $rootDir;
    }

    public function getRootDir()
    {
        return $this->rootDir;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $bootstrapFilepath = StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'bootstrap.yml';
        if (!is_readable($bootstrapFilepath)) {
            return;
        }

        Application::setRepositoryDir(StandaloneHelper::repositoryDir());
        $app = new Application();

        // injecting Standalone others commands...
        $app->getEventDispatcher()->addListener(CommandLineReadyEvent::EVENT_NAME, [
            CommandLineListener::class,
            'onCommandLineReady',
        ]);

        $app->getEventDispatcher()->dispatch(
            CommandLineReadyEvent::EVENT_NAME,
            new CommandLineReadyEvent($this, $app)
        );
    }
}
