<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserPreferenceController extends AbstractController
{
    protected $usrPrefMgr;
    protected $request;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->usrPrefMgr = $app->getContainer()->get('user_preference.manager');
        $this->request = $app->getRequest();
    }

    public function getCollection()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->usrPrefMgr->all());
    }

    public function get($name)
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->usrPrefMgr->dataOf($name));
    }

    public function put($name)
    {
        $this->assertIsAuthenticated();

        try {
            $this->usrPrefMgr->setDataOf($name, $this->request->request->all());
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => preg_replace('~^\[[a-zA-Z:\\\]+\] ~', '', $e->getMessage()),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function delete($name)
    {
        $this->assertIsAuthenticated();

        $this->usrPrefMgr->removeDataOf($name);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
