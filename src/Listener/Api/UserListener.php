<?php

namespace BackBeeCloud\Listener\Api;

use BackBee\Event\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserListener
{
    /**
     * Occurs on "rest.user.creation" to enable the user.
     */
    public static function onRestUserCreationEvent(Event $event)
    {
        $user = $event->getTarget();
        $user->setActivated(true);
        $user->setApiKeyEnabled(true);

        $event->getApplication()->getEntityManager()->flush($user);
    }
}
