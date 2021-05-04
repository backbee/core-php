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
