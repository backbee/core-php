<?php

namespace BackBeeCloud\Listener;

use BackBeePlanet\GlobalSettings;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class PrivacyPolicyListener
{
    /**
     * Called on "kernel.response" event.
     *
     * @param  FilterResponseEvent $event
     */
    public static function onKernelResponse(FilterResponseEvent $event)
    {
        $app = $event->getKernel()->getApplication();
        if (
            null === $app->getBBUserToken()
            || !(new GlobalSettings())->isPrivacyPolicyEnabled()
        ) {
            return;
        }

        $response = $event->getResponse();
        if (false === strpos($response->getContent(), '</body>')) {
            return;
        }

        $response->setContent(
            str_replace(
                '</body>',
                $app->getRenderer()->partial('common/privacy_policy_hook.js.twig') . '</body>',
                $response->getContent()
            )
        );
    }
}
