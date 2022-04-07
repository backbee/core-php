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
use BackBee\NestedNode\KeyWord;
use BackBee\Security\SecurityContext;
use BackBeeCloud\Tag\TagManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class TagLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class TagLogListener extends AbstractLogListener implements LogListenerInterface
{
    private const ENTITY_CLASS = KeyWord::class;

    /**
     * @var TagManager
     */
    private static $tagManager;

    /**
     * TagLogListener constructor.
     *
     * @param SecurityContext        $context
     * @param EntityManagerInterface $entityManager
     * @param TagManager             $tagManager
     * @param LoggerInterface|null   $logger
     */
    public function __construct(
        SecurityContext $context,
        EntityManagerInterface $entityManager,
        TagManager $tagManager,
        ?LoggerInterface $logger
    ) {
        self::$tagManager = $tagManager;
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
            $id = $request->attributes->get('uid');
            $rawData = array_merge(['uid' => $id], $request->request->all());

            self::writeLog(
                self::UPDATE_ACTION,
                $id,
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
            $tagId = $event->getRequest()->attributes->get('uid');
            $tag = self::$tagManager->get($tagId);

            if ($tag) {
                self::writeLog(
                    self::DELETE_ACTION,
                    $tagId,
                    self::ENTITY_CLASS,
                    self::getContent($tag->jsonSerialize())
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
                'name' => $rawData['keyword'] ?? $rawData['name'],
                'translations' => $rawData['translations'] ?? [],
                'parent_uid' => $rawData['parent_uid'] ?? null,
                'parents' => $rawData['parents'] ?? [],
                'has_children' => $rawData['has_children'] ?? null,
            ],
        ];
    }
}
