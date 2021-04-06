<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBee\Security\SecurityContext;
use BackBee\Security\Token\BBUserToken;
use BackBeeCloud\Security\Authentication\UserRightUnauthorizedException;
use BackBeeCloud\Security\Authorization\UserRightAccessDeniedException;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AbstractController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 *
 * @SWG\Info(
 *     title="BackBee API",
 *     version="1.0.0",
 *     description="This documentation lists all the routes used by the BackBee Toolbar."
 * )
 */
abstract class AbstractController
{
    /**
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * AbstractController constructor.
     *
     * @param BBApplication $app
     */
    public function __construct(BBApplication $app)
    {
        $this->setSecurityContext($app->getSecurityContext());
    }

    /**
     * Set security context.
     *
     * @param SecurityContext $securityContext
     */
    protected function setSecurityContext(SecurityContext $securityContext): void
    {
        $this->securityContext = $securityContext;
    }

    /**
     * Returns an instance of JsonResponse if the current user is not authenticated, else null.
     *
     * @return JsonResponse|null
     */
    protected function getResponseOnAnonymousUser(): ?JsonResponse
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

    /**
     * Create error json response.
     *
     * @param $error
     * @param $reason
     * @param $statusCode
     *
     * @return JsonResponse
     */
    protected function createErrorJsonResponse($error, $reason, $statusCode): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => $error,
                'reason' => $reason,
            ], $statusCode
        );
    }

    /**
     * Check if is authenticated.
     */
    protected function assertIsAuthenticated(): void
    {
        if (!$this->securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw UserRightUnauthorizedException::create();
        }
    }

    /**
     * Get user.
     *
     * @return mixed
     */
    protected function getUser()
    {
        return $this->securityContext->getToken()->getUser();
    }

    /**
     * Deny access unless granted.
     *
     * @param      $attribute
     * @param null $subject
     */
    protected function denyAccessUnlessGranted($attribute, $subject = null): void
    {
        $this->assertIsAuthenticated();

        if (!$this->securityContext->isGranted($attribute, $subject)) {
            throw UserRightAccessDeniedException::create();
        }
    }
}
