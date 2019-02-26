<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Security\Authentication\UserRightUnauthorizedException;
use BackBeeCloud\Security\Authorization\UserRightAccessDeniedException;
use BackBee\BBApplication;
use BackBee\Security\Token\BBUserToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractController
{
    /**
     * @var \BackBee\Security\SecurityContext
     */
    protected $securityContext;

    public function __construct(BBApplication $app)
    {
        $this->securityContext = $app->getSecurityContext();
    }

    /**
     * Returns an instance of JsonResponse if the current user is not authenticated, else null.
     *
     * @return JsonResponse|null
     */
    protected function getResponseOnAnonymousUser()
    {
        $response = null;
        if (!($this->securityContext->getToken() instanceof BBUserToken)) {
            $response = $this->createErrorJsonResponse(
                'unauthorized',
                'You must be authenticated to complete this action.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $response;
    }

    protected function createErrorJsonResponse($error, $reason, $statusCode)
    {
        return new JsonResponse([
            'error' => $error,
            'reason' => $reason,
        ], $statusCode);
    }

    protected function assertIsAuthenticated()
    {
        if (!$this->securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw UserRightUnauthorizedException::create();
        }
    }

    protected function getUser()
    {
        return $this->securityContext->getToken()->getUser();
    }

    protected function denyAccessUnlessGranted($attribute, $subject = null)
    {
        $this->assertIsAuthenticated();

        if (!$this->securityContext->isGranted($attribute, $subject)) {
            throw UserRightAccessDeniedException::create();
        }
    }
}
