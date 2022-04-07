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

namespace BackBeePlanet\Standalone;

use BackBee\BBApplication;
use BackBee\Event\Event;

/**
 * Class CommandLineReadyEvent
 *
 * @package BackBeePlanet\Standalone
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CommandLineReadyEvent extends Event
{
    public const EVENT_NAME = 'console.commandline.ready';

    /**
     * @var CommandLineConfig
     */
    protected $config;

    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * CommandLineReadyEvent constructor.
     *
     * @param CommandLineConfig $config
     * @param BBApplication     $app
     */
    public function __construct(CommandLineConfig $config, BBApplication $app)
    {
        $this->config = $config;
        $this->app = $app;

        parent::__construct($config, ['app' => $app]);
    }

    /**
     * @return CommandLineConfig
     */
    public function getCommandLineConfig(): CommandLineConfig
    {
        return $this->config;
    }

    /**
     * @return BBApplication
     */
    public function getApplication(): BBApplication
    {
        return $this->app;
    }
}
