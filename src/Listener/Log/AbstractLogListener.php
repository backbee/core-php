<?php

namespace BackBee\Listener\Log;

use BackBee\Security\SecurityContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

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
     * Write log.
     *
     * @param string $action  Type of action taken
     * @param string $object  The identifier of the object
     * @param string $type    The type of the object
     * @param array  $content The content of the object
     */
    protected static function writeLog(string $action, string $object, string $type, array $content = []): void
    {
        if ($token = self::$context->getToken()) {
            self::$logger->info(
                'AdminLog',
                [
                    'user' => $token->getUser()->getId() . '@' . $token->getUser()->getUsername(),
                    'action' => $action,
                    'object' => $object,
                    'type' => $type,
                    'content' => $content['content'] ?? null,
                    'parameters' => $content['parameters'] ?? null,
                ]
            );
        }
    }
}