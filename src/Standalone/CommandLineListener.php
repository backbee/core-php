<?php

namespace BackBeePlanet\Standalone;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CommandLineListener
{
    /**
     * Listens to 'console.commandline.ready' event to register Standalone others
     * commands.
     *
     * @param CommandLineReadyEvent $event
     */
    public static function onCommandLineReady(CommandLineReadyEvent $event): void
    {
    }
}
