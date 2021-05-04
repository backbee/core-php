<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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