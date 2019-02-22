<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Security\UserRightConstants;
use BackBeeCloud\ThemeColor\ColorPanelManager;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorPanelController extends AbstractController
{
    /**
     * @var ColorPanel
     */
    protected $colorPanelManager;

    public function __construct(ColorPanelManager $colorPanelManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->colorPanelManager = $colorPanelManager;
    }

    public function getAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->colorPanelManager->getColorPanel());
    }

    public function getAllColorsAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->colorPanelManager->getColorPanel()->getAllColors());
    }

    public function putAction(Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::CUSTOM_DESIGN_FEATURE
        );

        $data = $request->request->all();

        try {
            $this->colorPanelManager->updateColorPanel($data);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function changeThemeColorAction(Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::CUSTOM_DESIGN_FEATURE
        );

        $uniqueName = $request->request->get('unique_name');
        $conservePrimaryColor = $request->request->get('conserve_primary_color');

        try {
            $this->colorPanelManager->changeThemeColor($uniqueName, $conservePrimaryColor);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
