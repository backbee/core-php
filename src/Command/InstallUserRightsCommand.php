<?php

namespace BackBee\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class InstallUserRightsCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class InstallUserRightsCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:iur')
            ->setDescription('Install user rights if it is not yet done.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        $this->getBBApp()->getContainer()->get('core.installer.user_rights')->install($io);

        return 0;
    }
}
