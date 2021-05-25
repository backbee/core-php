<?php

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
class GroupTypeLogListener extends AbstractLogListener
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
     * On rest create action post call.
     */
    public static function onRestCreatePostCall(PostResponseEvent $event): void
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
     * On rest update action post call.
     */
    public static function onRestUpdatePostCall(PostResponseEvent $event): void
    {
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

    /**
     * On rest delete action pre call.
     */
    public static function onRestDeletePreCall(PreRequestEvent $event): void
    {
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
                'name' => $rawData['name'] ?? null,
                'description' => $rawData['description'] ?? null,
                'features_rights' => $rawData['features_rights'] ?? [],
                'pages_rights' => $rawData['pages_rights'] ?? [],
            ],
        ];
    }
}
