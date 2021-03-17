<?php

namespace BackBee\Command;

use BackBeePlanet\Standalone\StandaloneHelper;
use BackBeePlanet\Standalone\UpdateDatabaseSchemaTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class UpdateDatabaseSchemaCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class UpdateDatabaseSchemaCommand extends AbstractCommand
{
    use UpdateDatabaseSchemaTrait;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:update-database-schema')
            ->setAliases(['bb:uds'])
            ->setDescription('[bb:uds] - Updates database schema with entities from bundles and BackBee Standalone');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        $io->note('You may need to run "backbee:clear-all-cache" first.');

        $this->updateDatabaseSchema($this->getBBApp());

        $io->success(
            sprintf('Update of "%s" application database schema is now done.', StandaloneHelper::appname())
        );

        return 0;
    }
}
