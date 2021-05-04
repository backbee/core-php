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
use BackBeeCloud\Listener\RequestListener;
use BackBeeCloud\PageType\TypeManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use function count;

/**
 * Class PageTypeController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageTypeController extends AbstractController
{
    /**
     * @var TypeManager
     */
    protected $pageTypeManager;

    /**
     * PageTypeController constructor.
     *
     * @param TypeManager   $pageTypeManager
     * @param BBApplication $app
     */
    public function __construct(TypeManager $pageTypeManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->pageTypeManager = $pageTypeManager;
    }

    /**
     * Get collection.
     *
     * @return JsonResponse
     */
    public function getCollection(): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $all = array_values($this->pageTypeManager->all(true)),
            Response::HTTP_OK,
            [
                'Accept-Range' => 'pages-types ' . RequestListener::COLLECTION_MAX_ITEM,
                'Content-Range' => 0 === count($all) ? '-/-' : '0-' . (count($all) - 1) . '/' . count($all),
            ]
        );
    }
}
