<?php

namespace BackBeePlanet\Listener;

use BackBeePlanet\Command\ConvertCommandHandler;
use BackBeePlanet\Standalone\CommandLineReadyEvent;
use Webmozart\Console\Api\Args\Format\Option;

/**
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class CommandLineListener
{
    /**
     * Listens to 'console.commandline.ready' event to register images commands.
     *
     * @param CommandLineReadyEvent $event
     */
    public static function onCommandLineReady(CommandLineReadyEvent $event): void
    {
        $app = $event->getApplication();

        $event->getCommandLineConfig()
            ->beginCommand('optimize-image')
            ->setDescription('Entry command to manage site images')
            ->setHandler(
                function () use ($app) {
                    return new ConvertCommandHandler($app);
                }
            )
            ->beginSubCommand('convert')
            ->setDescription('Tries to convert all site images in order to optimize them')
            ->addOption('memory-limit', null, Option::REQUIRED_VALUE, 'The memory limit to set')
            ->setHandlerMethod('handleConvert')
            ->end()
            ->end();
    }
}