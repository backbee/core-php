<?php

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
            ->setName('worker:media-image-migration')
            ->setAliases(['bb:mim'])
            ->setDescription('[bb:mim] - Convert Media/Image to Basic/Image if needed');
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
