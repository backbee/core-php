<?php

namespace BackBee\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class IndexElasticsearchCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class IndexElasticsearchCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:ie')
            ->setDescription('Indexes all pages and tags into Elasticsearch.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        $this->getBBApp()->getContainer()->get('core.installer.elasticsearch')->index($io);

        $this->cleanup();

        return 0;
    }
}
