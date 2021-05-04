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
use BackBeeCloud\Security\UserRightConstants;
use BackBeeCloud\ThemeColor\ColorPanel;
use BackBeeCloud\ThemeColor\ColorPanelManager;
use Exception;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ColorPanelController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author  Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 *
 * @SWG\Tag(name="Color panel")
 */
class ColorPanelController extends AbstractController
{
    /**
     * @var ColorPanel
     */
    protected $colorPanelManager;

    /**
     * ColorPanelController constructor.
     *
     * @param ColorPanelManager $colorPanelManager
     * @param BBApplication     $app
     */
    public function __construct(ColorPanelManager $colorPanelManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->colorPanelManager = $colorPanelManager;
    }

    /**
     * @SWG\Get (
     *     path="/api/color-panel",
     *     tags={"Color panel"},
     *     description="Get color panel.",
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         examples={"application/json": {{"primary":{"id":"color-primary","color":"#39829d"},"secondary":{"id":"color-secondary","color":"#e0e1e6"},"textColor":{"id":"color-text","color":"#515256"},"backgroundColor":{"id":"color-background","color":"#ffffff"},"customColors":{}}}}
     *     )
     * )
     *
     * @return JsonResponse
     */
    public function getAction(): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $this->colorPanelManager->getColorPanel()
        );
    }

    /**
     * @SWG\Get (
     *     path="/api/color-panel/colors",
     *     tags={"Color panel"},
     *     description="Get all colors.",
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         examples={"application/json": {{"id":"color-primary","color":"#39829d"},{"id":"color-secondary","color":"#e0e1e6"},{"id":"color-text","color":"#515256"},{"id":"color-background","color":"#ffffff"}}}
     *     )
     * )
     *
     * @return JsonResponse
     */
    public function getAllColorsAction(): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $this->colorPanelManager->getColorPanel()->getAllColors()
        );
    }

    /**
     * @SWG\Put (
     *     path="/api/color-panel",
     *     tags={"Color panel"},
     *     description="Update color panel.",
     *     consumes={"application/x-www-form-urlencoded"},
     *     @SWG\Parameter (in="formData", name="primary", type="string", required=true),
     *     @SWG\Parameter (
     *         in="formData",
     *         name="custom_colors",
     *         type="array",
     *         @SWG\Items(type="string"),
     *         required=false
     *     ),
     *     @SWG\Response (
     *         response="204",
     *         description="No Content",
     *     ),
     *     @SWG\Response (
     *         response="400",
     *         description="Bad Request",
     *     )
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function putAction(Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::CUSTOM_DESIGN_FEATURE
        );

        $data = $request->request->all();

        try {
            $this->colorPanelManager->updateColorPanel($data);
        } catch (Exception $exception) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => $exception->getMessage(),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT
        );
    }

    /**
     * @SWG\Put (
     *     path="/api/color-panel/change-theme",
     *     tags={"Color panel"},
     *     description="Change theme color.",
     *     consumes={"application/x-www-form-urlencoded"},
     *     @SWG\Parameter (in="formData", name="primary", type="string", required=true),
     *     @SWG\Parameter (
     *         in="formData",
     *         name="custom_colors",
     *         type="array",
     *         @SWG\Items(type="string"),
     *         required=false
     *     ),
     *     @SWG\Response (
     *         response="204",
     *         description="No Content",
     *     ),
     *     @SWG\Response (
     *         response="400",
     *         description="Bad Request",
     *     )
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function changeThemeColorAction(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::CUSTOM_DESIGN_FEATURE
        );

        $uniqueName = $request->request->get('unique_name');
        $conservePrimaryColor = $request->request->get('conserve_primary_color');

        try {
            $this->colorPanelManager->changeThemeColor(
                $uniqueName,
                $conservePrimaryColor
            );
        } catch (Exception $exception) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => $exception->getMessage(),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT
        );
    }
}
