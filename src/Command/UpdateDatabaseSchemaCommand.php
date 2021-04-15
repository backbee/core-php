<?php

namespace BackBee\Command;

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
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:uds')
            ->setDescription('Updates database schema with entities from bundles and BackBee Standalone.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        $io->note('You may need to run "backbee:cc" first.');

        $this->getContainer()->get('core.installer.database')->updateDatabaseSchema($io);

        return 0;
    }
}
