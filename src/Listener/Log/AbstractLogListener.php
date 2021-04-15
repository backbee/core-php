<?php

namespace BackBee\Listener\Log;

use BackBee\Security\SecurityContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Util\ClassUtils;

/**
 * Class AbstractLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
abstract class AbstractLogListener
{
    public const CREATE_ACTION = 'create';
    public const UPDATE_ACTION = 'update';
    public const DELETE_ACTION = 'delete';

    /**
     * @var SecurityContext
     */
    protected static $context;

    /**
     * @var EntityManagerInterface
     */
    protected static $entityManager;

    /**
     * @var null|LoggerInterface
     */
    protected static $logger;

    /**
     * ClassContentListener constructor.
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
        self::$context = $context;
        self::$entityManager = $entityManager;
        self::$logger = $logger;
    }

    /**
     * Get action.
     *
     * @param $entity
     *
     * @return string
     */
    protected static function getAction($entity): string
    {
        if (self::$entityManager->getUnitOfWork()->isScheduledForInsert($entity)) {
            $action = self::CREATE_ACTION;
        } elseif (self::$entityManager->getUnitOfWork()->isScheduledForUpdate($entity)) {
            $action = self::UPDATE_ACTION;
        } else {
            $action = self::DELETE_ACTION;
        }

        return $action;
    }

    /**
     * Write log.
     */
    protected static function writeLog($entity, array $beforeData, array $afterData): void
    {
        if ($token = self::$context->getToken()) {
            self::$logger->info(
                'AdminLog',
                [
                    'user' => $token->getUser()->getId() . '@' . $token->getUser()->getUsername(),
                    'action' => self::getAction($entity),
                    'object' => $entity->getUid(),
                    'type' => ClassUtils::getRealClass($entity),
                    'before_content' => $beforeData['content'],
                    'before_parameter' => $beforeData['parameter'],
                    'after_content' => $afterData['content'],
                    'after_parameter' => $afterData['parameter'],
                ]
            );
        }
    }
}