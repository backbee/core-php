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

namespace BackBee\Listener\Log;

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Security\Group;
use BackBee\Security\SecurityContext;
use BackBeeCloud\Security\GroupType\GroupTypeManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class GroupTypeLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class GroupTypeLogListener extends AbstractLogListener implements LogListenerInterface
{
    private const ENTITY_CLASS = Group::class;

    /**
     * @var GroupTypeManager
     */
    private static $groupTypeManager;

    /**
     * GroupTypeLogListener constructor.
     *
     * @param SecurityContext        $context
     * @param EntityManagerInterface $entityManager
     * @param GroupTypeManager       $groupTypeManager
     * @param LoggerInterface|null   $logger
     */
    public function __construct(
        SecurityContext $context,
        EntityManagerInterface $entityManager,
        GroupTypeManager $groupTypeManager,
        ?LoggerInterface $logger
    ) {
        self::$groupTypeManager = $groupTypeManager;
        parent::__construct($context, $entityManager, $logger);
    }

    /**
     * {@inheritDoc}
     */
    public static function onPostActionPostCall(PostResponseEvent $event): void
    {
        if (self::$logger) {
            $rawData = json_decode($event->getResponse()->getContent(), true);

            self::writeLog(
                self::CREATE_ACTION,
                $rawData['id'] ?? null,
                self::ENTITY_CLASS,
                self::getContent($rawData)
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function onPutActionPostCall(PostResponseEvent $event): void
    {
        if (self::$logger) {
            $request = $event->getRequest();
            $groupTypeId = $request->attributes->get('id');
            $rawData = array_merge(['id' => $groupTypeId], $request->request->all());

            self::writeLog(
                self::UPDATE_ACTION,
                $groupTypeId,
                self::ENTITY_CLASS,
                self::getContent($rawData)
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function onDeleteActionPreCall(PreRequestEvent $event): void
    {
        if (self::$logger) {
            $groupTypeId = $event->getRequest()->attributes->get('id');
            $groupType = self::$groupTypeManager->getById($groupTypeId);

            if ($groupType) {
                self::writeLog(
                    self::DELETE_ACTION,
                    $groupTypeId,
                    self::ENTITY_CLASS,
                    self::getContent($groupType->jsonSerialize())
                );
            }
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
                'name' => $rawData['name'] ?? null,
                'description' => $rawData['description'] ?? null,
                'features_rights' => $rawData['features_rights'] ?? [],
                'pages_rights' => $rawData['pages_rights'] ?? [],
            ],
        ];
    }
}
