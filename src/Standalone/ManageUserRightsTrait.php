<?php

namespace BackBeePlanet\Standalone;

use BackBee\BBApplication;
use BackBeeCloud\Security\UserRightInstaller;
use Exception;
use RuntimeException;

/**
 * Trait ManageUserRightsTrait
 *
 * @package BackBeePlanet\Standalone
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
trait ManageUserRightsTrait
{
    /**
     * Install user right feature.
     *
     * @param BBApplication $app
     */
    protected function installUserRights(BBApplication $app): void
    {
        try {
            $securityConfig = $app->getConfig()->getSecurityConfig();
        } catch (Exception $exception) {
            $app->getLogging()->error(
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

        $installer = $app->getContainer()->get('core.user_right.installer');

        if ($installer->isInstalled()) {
            $installer->syncGroupTypes($securityConfig['group_types']);
            return;
        }

        $installer->install($securityConfig['group_types'], $securityConfig['default_group_type']);
    }
}
