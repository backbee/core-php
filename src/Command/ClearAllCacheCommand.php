<?php

namespace BackBee\Command;

use BackBeePlanet\Redis\RedisManager;
use BackBeePlanet\Standalone\StandaloneHelper;
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

        RedisManager::removePageCache(StandaloneHelper::appname());
        $io->section('Removed Redis page cache.');

        $this->cleanup();
        $io->section('Removed filesystem cache.');

        $io->success('All caches are now cleaned.');

        return 0;
    }
}
