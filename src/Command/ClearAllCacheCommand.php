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

use App\Helper\StandaloneHelper;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class ClearAllCacheCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ClearAllCacheCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:cc')
            ->setDescription('Removes all caches (file, database and redis).');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->getEntityManager()->getConnection()->executeUpdate('truncate cache;');
            $io->section('Truncated MySQL "cache" table.');
        } catch (Exception $exception) {
            $io->error($exception->getMessage());
        }

        $appName = $this->getContainer()->getParameter('app_name');

        try {
            $this->getContainer()->get('core.redis.manager')->removePageCache($appName);
            $io->section('Removed Redis page cache.');
        } catch (Exception $exception) {
            $io->error($exception->getMessage());
        }

        try {
            $this->getContainer()->get('core.sitemap.manager')->deleteCache();
            $io->section('Removed Redis sitemap cache.');
        } catch (Exception $exception) {
            $io->error($exception->getMessage());
        }

        $this->cleanup();
        $io->section('Removed filesystem cache.');

        $io->success('All caches are now cleaned.');

        return 0;
    }
}
