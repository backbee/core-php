<?php

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
class TagLogListener extends AbstractLogListener
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
     * On rest post action post call.
     */
    public static function onRestPostActionPostCall(PostResponseEvent $event): void
    {
        $rawData = json_decode($event->getResponse()->getContent(), true);

        self::writeLog(
            self::CREATE_ACTION,
            $rawData['uid'] ?? null,
            self::ENTITY_CLASS,
            self::getContent($rawData)
        );
    }

    /**
     * On rest put action post call.
     */
    public static function onRestPutActionPostCall(PostResponseEvent $event): void
    {
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

    /**
     * On rest delete action pre call.
     */
    public static function onRestDeleteActionPreCall(PreRequestEvent $event): void
    {
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
                'name' => $rawData['keyword'] ?? $rawData['name'],
                'translations' => $rawData['translations'] ?? [],
                'parent_uid' => $rawData['parent_uid'] ?? null,
                'parents' => $rawData['parents'] ?? [],
                'has_children' => $rawData['has_children'] ?? null,
            ],
        ];
    }
}
