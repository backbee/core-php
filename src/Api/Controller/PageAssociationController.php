<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

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
        $this->assertIsAuthenticated();

        if (null === $page = $this->pageMgr->get($pageuid)) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $pages = $this->pageAssociationMgr->customSearchPages($page, $request->query->get('term', ''));

        return new JsonResponse($this->pageMgr->formatCollection($pages, true));
    }

    public function getAssociatedPagesAction($pageuid)
    {
        $this->assertIsAuthenticated();

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
        $this->assertIsAuthenticated();

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
        $this->assertIsAuthenticated();

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
        $this->assertIsAuthenticated();

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
