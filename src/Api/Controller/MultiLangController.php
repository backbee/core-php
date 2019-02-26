<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangController extends AbstractController
{
    protected $multilangMgr;

    public function __construct(MultiLangManager $multilangMgr, BBApplication $app)
    {
        parent::__construct($app);

        $this->multilangMgr = $multilangMgr;
    }

    public function getCollection()
    {
        $this->assertIsAuthenticated();

        $all = $this->multilangMgr->getAllLangs();

        return new JsonResponse($all, Response::HTTP_OK, [
            'Accept-Range' => 'langs 100',
            'Content-Range' => sprintf('0-%d/%d', count($all) - 1, count($all)),
        ]);
    }

    public function get($id)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        $data = $this->multilangMgr->getLang($id);
        if (null === $data) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    public function enable($id)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        return $this->updateLangAction($id, true);
    }

    public function disable($id)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        return $this->updateLangAction($id, false);
    }

    public function defineDefault($id)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        try {
            $this->multilangMgr->setDefaultLang($id);
        } catch (\Exception $e) {
            if (!in_array(get_class($e), [\InvalidArgumentException::class, \LogicException::class])) {
                throw $e;
            }

            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function getWorkProgress()
    {
        $percent = null;
        try {
            $percent = $this->multilangMgr->getWorkProgress();
        } catch (\LogicException $e) {
            // nothing to do
        }

        return new JsonResponse([
            'work_progression' => $percent,
        ]);
    }

    private function updateLangAction($id, $newState)
    {
        try {
            $this->multilangMgr->updateLang($id, $newState);
        } catch (\Exception $e) {
            if (!in_array(get_class($e), [\InvalidArgumentException::class, \LogicException::class])) {
                throw $e;
            }

            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
