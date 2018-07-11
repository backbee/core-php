<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\ThemeColor\ColorPanelManager;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        return new JsonResponse($this->colorPanelManager->getColorPanel());
    }

    public function getAllColorsAction()
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        return new JsonResponse($this->colorPanelManager->getColorPanel()->getAllColors());
    }

    public function putAction(Request $request)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

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
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

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
