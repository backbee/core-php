<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Security\UserRightConstants;
use BackBee\Event\Event;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class UserRightListener
{
    public static function onApplicationInit(Event $event)
    {
        $app = $event->getTarget();
        if ($app->isRestored()) {
            return;
        }

        $routing = $app->getRouting();
        $enableUserRightForBundles = [];
        foreach ($app->getBundles() as $bundle) {
            if (true === $bundle->getProperty('enable_user_right')) {
                $enableUserRightForBundles[] = $bundle->getId();

                continue;
            }

            $expectedAdminEntryPointRouteName = sprintf(
                'bundle.%s.admin_entrypoint',
                $bundle->getId()
            );

            if ($routing->get($expectedAdminEntryPointRouteName)) {
                $enableUserRightForBundles[] = $bundle->getId();
            }
        }

        $app->getContainer()->setParameter(
            'user_right.super_admin_bundles_rights',
            array_map(
                [
                    UserRightConstants::class,
                    'createBundleSubject',
                ],
                $enableUserRightForBundles
            )
        );
    }
}
