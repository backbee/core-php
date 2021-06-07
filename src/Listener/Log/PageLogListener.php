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
use BackBee\NestedNode\Page;
use BackBee\Security\SecurityContext;
use BackBeeCloud\Entity\PageManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PageLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageLogListener extends AbstractLogListener implements LogListenerInterface
{
    private const ENTITY_CLASS = Page::class;

    /**
     * @var PageManager
     */
    private static $pageManager;

    /**
     * PageLogListener constructor.
     *
     * @param SecurityContext        $context
     * @param EntityManagerInterface $entityManager
     * @param PageManager            $pageManager
     * @param LoggerInterface|null   $logger
     */
    public function __construct(
        SecurityContext $context,
        EntityManagerInterface $entityManager,
        PageManager $pageManager,
        ?LoggerInterface $logger
    ) {
        self::$pageManager = $pageManager;
        parent::__construct($context, $entityManager, $logger);
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
            ['content' => $rawData]
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function onPutActionPostCall(PostResponseEvent $event): void
    {
        $rawData = json_decode($event->getResponse()->getContent(), true);

        self::writeLog(
            self::UPDATE_ACTION,
            $rawData['id'] ?? null,
            self::ENTITY_CLASS,
            ['content' => $rawData]
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function onDeleteActionPreCall(PreRequestEvent $event): void
    {
        $pageId = $event->getRequest()->attributes->get('uid');
        $page = self::$pageManager->get($pageId);

        if ($page) {
            self::writeLog(
                self::DELETE_ACTION,
                $pageId,
                self::ENTITY_CLASS,
                ['content' => self::$pageManager->format($page)]
            );
        }
    }
}
