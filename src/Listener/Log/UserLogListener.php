<?php

namespace BackBee\Listener\Log;

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Security\User;

/**
 * Class UserLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class UserLogListener extends AbstractLogListener
{
    /**
     * On rest user post action post call.
     */
    public static function onRestUserPostActionPostCall(PostResponseEvent $event): void
    {
        $rawData = json_decode($event->getResponse()->getContent(), true);

        self::writeLog(
            self::CREATE_ACTION,
            $rawData['id'] ?? null,
            User::class,
            self::getContent($rawData)
        );
    }

    /**
     * On rest user put action post call.
     */
    public static function onRestUserPutActionPostCall(PostResponseEvent $event): void
    {
        $request = $event->getRequest();
        $id = $request->attributes->get('id');
        $rawData = array_merge(['id' => $id], $request->request->all());

        self::writeLog(
            self::UPDATE_ACTION,
            $id,
            User::class,
            self::getContent($rawData)
        );
    }

    /**
     * On rest user delete action post call.
     */
    public static function onRestUserDeleteActionPostCall(PostResponseEvent $event): void
    {
        self::writeLog(
            self::DELETE_ACTION,
            $event->getRequest()->attributes->get('id'),
            User::class
        );
    }

    /**
     * Get content.
     *
     * @param array $rawData
     *
     * @return array
     */
    public static function getContent(array $rawData): array
    {
        return [
            'content' => [
                'id' => $rawData['id'] ?? null,
                'login' => $rawData['login'] ?? null,
                'email' => $rawData['email'] ?? null,
                'firstname' => $rawData['firstname'] ?? null,
                'lastname' => $rawData['lastname'] ?? null,
                'group_types' => $rawData['group_types'] ?? null,
            ],
        ];
    }
}
