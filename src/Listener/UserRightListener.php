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

namespace BackBeeCloud\Listener;

use BackBee\Event\Event;
use BackBeeCloud\Security\UserRightConstants;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class UserRightListener
{
    /**
     * @param \BackBee\Event\Event $event
     *
     * @return void
     */
    public static function onApplicationInit(Event $event): void
    {
        $app = $event->getTarget();

        if ($app->isRestored()) {
            return;
        }

        $enableUserRightForBundles = $app->getContainer()->get('core.bundle.manager')->getActivatedBundles();

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
