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

use BackBee\BBApplication;
use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\Security\UserRightManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserRightController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
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

    /**
     * UserRightController constructor.
     *
     * @param UserRightManager $userRightManager
     * @param PageManager      $pageManager
     * @param BBApplication    $app
     */
    public function __construct(UserRightManager $userRightManager, PageManager $pageManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->userRightManager = $userRightManager;
        $this->pageManager = $pageManager;
    }

    /**
     * Get current user rights collection.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getCurrentUserRightsCollection(Request $request): JsonResponse
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

    /**
     * Get current user authorized categories collection.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getCurrentUserAuthorizedCategoriesCollection(Request $request): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $this->userRightManager->getUserAuthorizedCategories(
                $this->getUser(),
                $request->query->get('contextual_page_type')
            ),
            Response::HTTP_OK
        );
    }
}
