<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\MultiLang\PageAssociationManager;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageAssociationController extends AbstractController
{
    /**
     * @var PageAssociationManager
     */
    protected $pageAssociationMgr;

    /**
     * @var PageManager
     */
    protected $pageMgr;

    public function __construct(BBApplication $app, PageAssociationManager $pageAssociationMgr, PageManager $pageMgr)
    {
        parent::__construct($app);

        $this->pageAssociationMgr = $pageAssociationMgr;
        $this->pageMgr = $pageMgr;
    }

    public function customSearchPageAction($pageuid, Request $request)
    {
        if ($response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        if (null === $page = $this->pageMgr->get($pageuid)) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $pages = $this->pageAssociationMgr->customSearchPages($page, $request->query->get('term', ''));

        return new JsonResponse($this->pageMgr->formatCollection($pages, true));
    }

    public function getAssociatedPagesAction($pageuid)
    {
        if ($response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        if (null === $page = $this->pageMgr->get($pageuid)) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $associatedPages = $this->pageAssociationMgr->getAssociatedPages($page);

        return new JsonResponse(
            $this->pageMgr->formatCollection($associatedPages, true)
        );
    }

    public function getAssociatedPageAction($pageuid, $lang)
    {
        if ($response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $page = $this->pageMgr->get($pageuid);
        if (false == $page) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $associatedPage = $this->pageAssociationMgr->getAssociatedPage($page, $lang);

        return new JsonResponse(
            $associatedPage ? $this->pageMgr->format($associatedPage, true) : null
        );
    }

    public function associatePagesAction($pageuid, Request $request)
    {
        if ($response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        if (null === $page = $this->pageMgr->get($pageuid)) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $targetPageUid = $request->request->get('targetpageuid');
        if (null === $targetPage = $this->pageMgr->get($targetPageUid)) {
            return $this->getPageNotFoundResponse($targetPageUid);
        }

        try {
            $this->pageAssociationMgr->associatePages($page, $targetPage);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function deletePageAssociationAction($pageuid)
    {
        if ($response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        if (null === $page = $this->pageMgr->get($pageuid)) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        try {
            $this->pageAssociationMgr->deleteAssociatedPage($page);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    protected function getPageNotFoundResponse($pageuid)
    {
        return new JsonResponse([
            'error' => 'not_found',
            'reason' => "Page with uid `{$pageuid}` does not exist.",
        ], Response::HTTP_NOT_FOUND);
    }
}
