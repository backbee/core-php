<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\Security\UserRightManager;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightController extends AbstractController
{
    /**
     * @var UserRightManager
     */
    private $userRightManager;

    /**
     * @var PageManager
     */
    private $pageManager;

    public function __construct(UserRightManager $userRightManager, PageManager $pageManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->userRightManager = $userRightManager;
        $this->pageManager = $pageManager;
    }

    public function getCurrentUserRightsCollection(Request $request)
    {
        $page = null;
        if ($request->query->get('contextual_page_uid')) {
            $page = $this->pageManager->get($request->query->get('contextual_page_uid'));
        }

        $this->assertIsAuthenticated();

        return new JsonResponse(
            $this->userRightManager->getUserRights(
                $this->getUser(),
                $page
            ),
            Response::HTTP_OK
        );
    }

    public function getCurrentUserAuthorizedCategoriesCollection(Request $request)
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $this->userRightManager->getUserAuthorizedCategories(
                $this->getUser(),
                $request->query->get('contextual_page_type', null)
            ),
            Response::HTTP_OK
        );
    }
}
