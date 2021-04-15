<?php

namespace BackBee\Installer;

use Exception;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use function is_array;
use function is_string;

/**
 * Class UserRightsInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class UserRightsInstaller extends AbstractInstaller
{
    /**
     * Install.
     *
     * @param SymfonyStyle $io
     */
    public function install(SymfonyStyle $io): void
    {
        $io->section('Install user rights');

        try {
            $securityConfig = $this->getApplication()->getConfig()->getSecurityConfig();
        } catch (Exception $exception) {
            $io->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        if (!isset($securityConfig['group_types']) || !is_array($securityConfig['group_types'])) {
            throw new RuntimeException('"group_types" configuration is missing from security.yml.');
        }

        if (!isset($securityConfig['default_group_type']) || !is_string($securityConfig['default_group_type'])) {
            throw new RuntimeException('"default_group_type" configuration is missing from security.yml.');
        }

        $installer = $this->getApplication()->getContainer()->get('core.user_right.installer');

        if ($installer->isInstalled()) {
            $installer->syncGroupTypes($securityConfig['group_types']);
            $io->success('User right successfully updated.');
            return;
        }

        $installer->install($securityConfig['group_types'], $securityConfig['default_group_type']);

        $io->success('User right successfully installed.');
    }
}