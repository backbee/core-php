<?php

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
