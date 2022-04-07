<?php

/*
 * Copyright (c) 2022 Obione
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
use BackBeeCloud\Design\ButtonManager;
use BackBeeCloud\Security\UserRightConstants;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class DesignButtonController extends AbstractController
{
    /**
     * @var ButtonManager
     */
    protected $buttonManager;

    public function __construct(ButtonManager $buttonManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->buttonManager = $buttonManager;
    }

    public function getSettingsAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->buttonManager->getSettings());
    }

    public function getShapeValuesAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->buttonManager->getShapeValues());
    }

    public function updateSettingsAction(Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::CUSTOM_DESIGN_FEATURE
        );

        if (!$request->request->has('font') || !$request->request->has('shape')) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => "'font' and 'shape' parameters are required to update Button settings.",
                ], Response::HTTP_BAD_REQUEST
            );
        }

        $fontValue = $request->request->get('font');
        $shapeValue = $request->request->get('shape');

        try {
            $this->buttonManager->updateFont($fontValue);
            $this->buttonManager->updateShape($shapeValue);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => $exception->getMessage(),
                ], Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
