<?php

namespace BackBeeCloud\Listener\Api;

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Event\Event;
use BackBee\Security\User;
use Symfony\Component\HttpFoundation\Response;

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

    public static function onGetCollectionPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        if (
            Response::HTTP_OK !== $response->getStatusCode()
            && 'application/json' !== $response->headers->get('content-type')
        ) {
            return;
        }

        $bbtoken = $event->getApplication()->getBBUserToken();
        $currentUser = $bbtoken ? $bbtoken->getUser() : null;
        $entyMgr = $event->getApplication()->getEntityManager();
        $usrMgr = $event->getApplication()->getContainer()->get('cloud.user_manager');
        $data = json_decode($response->getContent(), true);
        foreach ($data as &$row) {
            $isRemovable = true;
            $user = $entyMgr->find(User::class, $row['id']);
            if ($user) {
                if ($usrMgr->isMainUser($user)) {
                    $isRemovable = false;
                } elseif ($currentUser && $currentUser->getId() === $user->getId()) {
                    $isRemovable = false;
                }
            }

            $row['is_removable'] = $isRemovable;
        }

        $response->setContent(json_encode($data));
    }
}
