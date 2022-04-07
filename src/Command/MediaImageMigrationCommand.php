<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBee\Command;

use BackBee\Site\Site;
use BackBeeCloud\Job\MediaImageMigrationJob;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class MediaImageMigrationCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class MediaImageMigrationCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('worker:mim')
            ->setDescription('Convert Media/Image to Basic/Image if needed.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Starting migration of Media/Image to Basic/Image process');
        $this->getContainer()->get('site_status.manager')->lock();
        $siteLabel = $this->getEntityManager()->getRepository(Site::class)->findOneBy([])->getLabel();
        $command = $this->getApplication()->find('worker:run-job');
        $command->setJob(new MediaImageMigrationJob($siteLabel));

        return $command->execute($input, $output);
    }
}
