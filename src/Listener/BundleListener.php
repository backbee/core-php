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

use BackBee\Controller\Event\PostResponseEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class BundleListener
{
    /**
     * Occurs on `rest.controller.bundlecontroller.getcollectionaction.postcall`.
     *
     * @param  PostResponseEvent $event
     */
    public static function onGetCollectionPostCall(PostResponseEvent $event)
    {
        $routing = $event->getApplication()->getRouting();
        $bundles = json_decode($event->getResponse()->getContent(), true);
        foreach ($bundles as &$bundle) {
            unset($bundle['bundle_admin_entrypoint']);

            $adminEntryPointUri = null;
            $routeName = self::buildBundleAdminEntryPointRouteName($bundle['id']);
            if ($routing->get($routeName)) {
                $adminEntryPointUri = $routing->getRoutePath($routeName);
            }

            if ($adminEntryPointUri) {
                $bundle['bundle_admin_entrypoint'] = $adminEntryPointUri;
            }
        }

        $event->getResponse()->setContent(json_encode($bundles));
    }

    /**
     * Builds and returns the expected bundle admin entrypoint route name base on
     * bundle id.
     *
     * @param  string $bundleId
     *
     * @return string
     */
    protected static function buildBundleAdminEntryPointRouteName($bundleId)
    {
        return sprintf('bundle.%s.admin_entrypoint', $bundleId);
    }
}
