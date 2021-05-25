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

namespace BackBee\Listener\Log;

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Security\Group;
use BackBee\Security\SecurityContext;
use BackBee\Security\User;
use BackBeeCloud\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class UserLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class UserLogListener extends AbstractLogListener implements LogListenerInterface
{
    private const ENTITY_CLASS = User::class;

    /**
     * @var UserManager
     */
    private static $userManager;

    /**
     * UserLogListener constructor.
     *
     * @param SecurityContext        $context
     * @param EntityManagerInterface $entityManager
     * @param UserManager            $userManager
     * @param LoggerInterface|null   $logger
     */
    public function __construct(
        SecurityContext $context,
        EntityManagerInterface $entityManager,
        UserManager $userManager,
        ?LoggerInterface $logger
    ) {
        parent::__construct($context, $entityManager, $logger);
        self::$userManager = $userManager;
    }

    /**
     * {@inheritDoc}
     */
    public static function onPostActionPostCall(PostResponseEvent $event): void
    {
        $rawData = json_decode($event->getResponse()->getContent(), true);

        self::writeLog(
            self::CREATE_ACTION,
            $rawData['id'] ?? null,
            self::ENTITY_CLASS,
            self::getContent($rawData)
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function onPutActionPostCall(PostResponseEvent $event): void
    {
        $request = $event->getRequest();
        $id = $request->attributes->get('id');
        $rawData = array_merge(['id' => $id], $request->request->all());

        self::writeLog(
            self::UPDATE_ACTION,
            $id,
            self::ENTITY_CLASS,
            self::getContent($rawData)
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function onDeleteActionPreCall(PreRequestEvent $event): void
    {
        $userId = $event->getRequest()->attributes->get('id');
        $user = self::$userManager->getById($userId);

        if ($user) {
            self::writeLog(
                self::DELETE_ACTION,
                $userId,
                self::ENTITY_CLASS,
                self::getContent(
                    [
                        'id' => $user->getId(),
                        'login' => $user->getLogin(),
                        'email' => $user->getEmail(),
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'group_types' => array_map(
                            static function (Group $group) {
                                return $group->getName();
                            },
                            $user->getGroups()->toArray()
                        )
                    ]
                )
            );
        }
    }

    /**
     * Get content.
     *
     * @param array $rawData
     *
     * @return array
     */
    private static function getContent(array $rawData): array
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
