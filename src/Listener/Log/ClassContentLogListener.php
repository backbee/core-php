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

use BackBee\ClassContent\AbstractClassContent;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Security\SecurityContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;

/**
 * Class ClassContentLogListener
 *
 * @package BackBee\Listener
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ClassContentLogListener extends AbstractLogListener implements LogListenerInterface
{
    /**
     * @var EntityRepository
     */
    private static $repository;

    /**
     * ClassContentLogListener constructor.
     *
     * @param SecurityContext        $context
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface|null   $logger
     */
    public function __construct(
        SecurityContext $context,
        EntityManagerInterface $entityManager,
        ?LoggerInterface $logger
    ) {
        self::$repository = $entityManager->getRepository(AbstractClassContent::class);
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
                $rawData['uid'] ?? null,
                $rawData['className'] ?? null,
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
            $rawData = json_decode($event->getResponse()->getContent(), true);

            self::writeLog(
                self::UPDATE_ACTION,
                $rawData['uid'] ?? null,
                $rawData['className'] ?? null,
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
            $contentId = $event->getRequest()->attributes->get('uid');
            $content = self::$repository->find($contentId);

            if ($content) {
                $rawData = $content->jsonSerialize();
                self::writeLog(
                    self::DELETE_ACTION,
                    $contentId,
                    $rawData['className'] ?? null,
                    self::getContent($rawData)
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
                'uid' => $rawData['uid'] ?? null,
                'type' => $rawData['type'] ?? null,
                'data' => $rawData['data'] ?? [],
                'properties' => $rawData['properties'] ?? [],
                'parameters' => $rawData['parameters'] ?? [],
            ],
        ];
    }
}
