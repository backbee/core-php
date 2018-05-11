<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBee\NestedNode\Page;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageAssociationController extends AbstractController
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entyMgr;

    /**
     * @var \BackBeeCloud\PageAssociationManager;
     */
    protected $pageAssociationMgr;

    /**
     * @var \BackBeeCloud\Entity\PageManager;
     */
    protected $pageMgr;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->app                = $app;
        $this->entyMgr            = $app->getEntityManager();
        $this->pageAssociationMgr = $app->getContainer()->get('cloud.multilang.page_association.manager');
        $this->pageMgr            = $app->getContainer()->get('cloud.page_manager');
    }

    public function getAssociatedPagesAction($pageuid)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $page = $this->entyMgr->find(Page::class, $pageuid);
        if (false == $page) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $associatedPages = $this->pageAssociationMgr->getAssociatedPages($page);

        return new JsonResponse(
            $this->formatCollection($associatedPages, true), Response::HTTP_OK
        );
    }

    public function getAssociatedPageAction($pageuid, $lang)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $page = $this->entyMgr->find(Page::class, $pageuid);
        if (false == $page) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $associatedPage = $this->pageAssociationMgr->getAssociatedPage($page,
            $lang);

        return new JsonResponse(
            $associatedPage ? $this->pageMgr->format($associatedPage, true) : null,
            Response::HTTP_OK
        );
    }

    public function setAssociatedPageAction($pageuid)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $page = $this->entyMgr->find(Page::class, $pageuid);
        if (false == $page) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $request       = $this->app->getRequest();
        $targetpageuid = $request->request->get('targetpageuid');
        $targetPage    = $this->entyMgr->find(Page::class, $targetpageuid);
        if (false == $targetPage) {
            return $this->getPageNotFoundResponse($targetpageuid);
        }

        try {
            $result = $this->pageAssociationMgr->setAssociatedPage($page,
                $targetPage);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'bad_request',
                'reason' => $e->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
        }
        return new JsonResponse(
            null, Response::HTTP_OK
        );
    }

    protected function getPageNotFoundResponse($pageuid)
    {
        return new JsonResponse([
            'error' => 'not_found',
            'reason' => "Page with uid `{$pageuid}` does not exist.",
            ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Applies ::format() on every item of the provided collection.
     *
     * @see ::format()
     *
     * @param mixed $collection
     * @param bool  $strictDraftMode
     *
     * @return array
     */
    public function formatCollection($collection, $strictDraftMode)
    {
        $result = [];
        foreach ($collection as $key => $page) {
            $result[$key] = $this->pageMgr->format($page, $strictDraftMode);
        }

        return $result;
    }
}