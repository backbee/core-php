<?php

namespace BackBeeCloud\Elasticsearch;

use BackBee\BBApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Trait IndexElasticsearchTrait
 *
 * @package BackBeeCloud\Elasticsearch
 *
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
trait IndexElasticsearchTrait
{
    /**
     * Index elasticsearch.
     *
     * @param BBApplication     $app
     * @param SymfonyStyle|null $io
     */
    protected function indexElasticsearch(BBApplication $app, SymfonyStyle $io): void
    {
        $elasticsearchManager = $app->getContainer()->get('elasticsearch.manager');

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
    }
}
