<?php

namespace BackBee\Installer;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class ElasticsearchInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ElasticsearchInstaller extends AbstractInstaller
{
    /**
     * Index elasticsearch.
     *
     * @param SymfonyStyle $io
     */
    public function index(SymfonyStyle $io): void
    {
        $io->section('Index elasticsearch');

        $elasticsearchManager = $this->getApplication()->getContainer()->get('elasticsearch.manager');

        $io->text('Delete and create index');
        $elasticsearchManager->resetIndex();

        $io->text('Create types');
        $elasticsearchManager->createTypes();

        $io->text('Reindexing all pages');
        $io->newLine();
        $io->progressStart($elasticsearchManager->getTotalOfUndeletedPages());
        $elasticsearchManager->indexAllPages(true, $io);
        $io->progressFinish();

        $io->text('Reindexing all tags');
        $elasticsearchManager->indexAllTags();
        $io->newLine();

        $io->success('Contents are now indexed into Elasticsearch.');
    }
}