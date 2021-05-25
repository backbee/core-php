<?php

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
class ClassContentLogListener extends AbstractLogListener
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
     * On rest post action post call.
     */
    public static function onPostActionPostCall(PostResponseEvent $event): void
    {
        $rawData = json_decode($event->getResponse()->getContent(), true);

        self::writeLog(
            self::CREATE_ACTION,
            $rawData['uid'] ?? null,
            $rawData['className'] ?? null,
            self::getContent($rawData)
        );
    }

    /**
     * On rest put action post call.
     */
    public static function onPutActionPostCall(PostResponseEvent $event): void
    {
        $rawData = json_decode($event->getResponse()->getContent(), true);

        self::writeLog(
            self::UPDATE_ACTION,
            $rawData['uid'] ?? null,
            $rawData['className'] ?? null,
            self::getContent($rawData)
        );
    }

    /**
     * On rest delete action pre call.
     */
    public static function onDeleteActionPreCall(PreRequestEvent $event): void
    {
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
                'uid' => $rawData['uid'] ?? null,
                'type' => $rawData['type'] ?? null,
                'data' => $rawData['data'] ?? [],
                'properties' => $rawData['properties'] ?? [],
                'parameters' => $rawData['parameters'] ?? [],
            ],
        ];
    }
}
