<?php

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
class PageLogListener extends AbstractLogListener
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
     * On post action post call.
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
     * On put action post call.
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
     * On delete action pre call.
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
