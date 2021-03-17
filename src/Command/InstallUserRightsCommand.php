<?php

namespace BackBee\Command;

use BackBeePlanet\Standalone\ManageUserRightsTrait;
use Exception;
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
    use ManageUserRightsTrait;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:install-user-rights')
            ->setAliases(['bb:iur'])
            ->setDescription('[bb:iur] - Install user rights if it is not yet done');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->installUserRights($this->getBBApp());
        } catch (Exception $exception) {
            $io->error(sprintf('<error>[%s] %s</error>', get_class($exception), $exception->getMessage()));
        }

        $io->success('User right successfully installed.');

        return 0;
    }
}
