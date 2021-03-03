<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBee\Exception\BBException;
use BackBeeCloud\UserPreference\UserPreferenceManager;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserPreferenceController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserPreferenceController extends AbstractController
{
    /**
     * @var UserPreferenceManager
     */
    protected $usrPrefMgr;

    /**
     * @var Request
     */
    protected $request;

    /**
     * UserPreferenceController constructor.
     *
     * @param BBApplication $app
     *
     * @throws BBException
     */
    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->usrPrefMgr = $app->getContainer()->get('user_preference.manager');
        $this->request = $app->getRequest();
    }

    /**
     * Get collection.
     *
     * @return JsonResponse
     */
    public function getCollection(): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->usrPrefMgr->all());
    }

    /**
     * GET method.
     *
     * @param $name
     *
     * @return JsonResponse
     */
    public function get($name): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->usrPrefMgr->dataOf($name));
    }

    /**
     * PUT method.
     *
     * @param $name
     *
     * @return JsonResponse|Response
     */
    public function put($name)
    {
        $this->assertIsAuthenticated();

        try {
            $this->usrPrefMgr->setDataOf($name, $this->request->request->all());
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => preg_replace('~^\[[a-zA-Z:\\\]+\] ~', '', $e->getMessage()),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete method.
     *
     * @param $name
     *
     * @return Response
     */
    public function delete($name): Response
    {
        $this->assertIsAuthenticated();

        $this->usrPrefMgr->removeDataOf($name);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
