<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Security\AclHandler;
use BackBee\BBApplication;
use BackBee\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class AclController extends AbstractController
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var AclHandler
     */
    protected $aclHandler;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->app = $app;
        $this->aclHandler = new AclHandler($app);
    }

    public function get($id)
    {
        if ($response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $user = $this->app->getEntityManager()->find(User::class, $id);
        if (null === $user) {
            return new JsonResponse([
                'error'  => 'not_found',
                'reason' => sprintf('Cannot find user with id %s', $id),
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->aclHandler->getPermissionsByUser($user));
    }
}
