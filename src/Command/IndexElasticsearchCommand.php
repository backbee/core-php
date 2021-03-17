<?php

namespace BackBee\Command;

use BackBeeCloud\Elasticsearch\IndexElasticsearchTrait;
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
    use IndexElasticsearchTrait;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:index-elasticsearch')
            ->setAliases(['bb:ie'])
            ->setDescription('[bb:ie] - Indexes all pages and tags into Elasticsearch');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        $this->indexElasticsearch($this->getBBApp(), $io);

        $io->success('Contents are now re-indexed into Elasticsearch.');

        $this->cleanup();

        return 0;
    }
}
