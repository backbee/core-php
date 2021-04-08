<?php

namespace BackBeeCloud\Listener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class PrivacyPolicyListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class PrivacyPolicyListener
{
    /**
     * Called on "kernel.response" event.
     *
     * @param FilterResponseEvent $event
     */
    public static function onKernelResponse(FilterResponseEvent $event): void
    {
        $app = $event->getKernel()->getApplication();
        if (
            null === $app->getBBUserToken()
            || !$app->getAppParameter('privacy_policy')
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
