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
