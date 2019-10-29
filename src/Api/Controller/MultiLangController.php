<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\BBApplication;
use Exception;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangController extends AbstractController
{
    /**
     * @var MultiLangManager
     */
    protected $multiLangMgr;

    /**
     * MultiLangController constructor.
     *
     * @param MultiLangManager $multiLangMgr
     * @param BBApplication    $app
     */
    public function __construct(MultiLangManager $multiLangMgr, BBApplication $app)
    {
        parent::__construct($app);

        $this->multiLangMgr = $multiLangMgr;
    }

    /**
     * @return JsonResponse
     */
    public function getCollection(): JsonResponse
    {
        $this->assertIsAuthenticated();

        $all = $this->multiLangMgr->getAllLangs();

        return new JsonResponse($all, Response::HTTP_OK, [
            'Accept-Range' => 'langs 100',
            'Content-Range' => sprintf('0-%d/%d', count($all) - 1, count($all)),
        ]);
    }

    /**
     * @param $id
     *
     * @return JsonResponse|Response
     */
    public function get($id)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        $data = $this->multiLangMgr->getLang($id);

        return null === $data ? new Response('', Response::HTTP_NOT_FOUND) : new JsonResponse($data, Response::HTTP_OK);
    }

    /**
     * @param $id
     *
     * @return Response
     * @throws Exception
     */
    public function enable($id): Response
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        return $this->updateLangAction($id, true);
    }

    /**
     * @param $id
     *
     * @return Response|null
     * @throws Exception
     */
    public function disable($id): ?Response
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        return $this->updateLangAction($id, false);
    }

    /**
     * @param $id
     *
     * @return Response
     * @throws Exception
     */
    public function defineDefault($id): Response
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::MULTILANG_FEATURE
        );

        try {
            $this->multiLangMgr->setDefaultLang($id);
        } catch (Exception $e) {
            if (!in_array(get_class($e), [InvalidArgumentException::class, LogicException::class])) {
                throw $e;
            }

            return new JsonResponse(
                ['error'  => 'bad_request', 'reason' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @return JsonResponse
     */
    public function getWorkProgress(): JsonResponse
    {
        $percent = null;
        try {
            $percent = $this->multiLangMgr->getWorkProgress();
        } catch (LogicException $e) {
            // nothing to do
        }

        return new JsonResponse([
            'work_progression' => $percent,
        ]);
    }

    /**
     * @param $id
     * @param $newState
     *
     * @return Response
     * @throws Exception
     */
    private function updateLangAction($id, $newState): Response
    {
        try {
            $this->multiLangMgr->updateLang($id, $newState);
        } catch (Exception $e) {
            if (!in_array(get_class($e), [InvalidArgumentException::class, LogicException::class])) {
                throw $e;
            }

            return new JsonResponse(
                ['error'  => 'bad_request', 'reason' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
