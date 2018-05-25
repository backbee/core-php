<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractController
{
    /**
     * @var \BackBee\Security\Token\BBUserToken|null
     */
    protected $bbtoken;

    public function __construct(BBApplication $app)
    {
        $this->bbtoken = $app->getBBUserToken();
    }

    /**
     * Returns an instance of JsonResponse if the current user is not authenticated, else null.
     *
     * @return JsonResponse|null
     */
    protected function getResponseOnAnonymousUser()
    {
        $response = null;
        if (null === $this->bbtoken) {
            $response = new JsonResponse([
                'error'  => 'unauthorized',
                'reason' => 'You must be authenticated to complete this action.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $response;
    }
}
