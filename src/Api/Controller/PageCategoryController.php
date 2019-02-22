<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class PageCategoryController extends AbstractController
{
    /**
     * @var PageCategoryManager
     */
    private $pageCategoryManager;

    public function __construct(PageCategoryManager $pageCategoryManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->pageCategoryManager = $pageCategoryManager;
    }

    public function getCollectionAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $this->pageCategoryManager->getCategories(),
            Response::HTTP_OK
        );
    }
}
