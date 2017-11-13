<?php

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
