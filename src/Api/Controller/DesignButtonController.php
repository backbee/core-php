<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Design\ButtonManager;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\BBApplication;
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
            return new JsonResponse([
                'error' => 'bad_request',
                'reason' => "'font' and 'shape' parameters are required to update Button settings.",
            ], Response::HTTP_BAD_REQUEST);
        }

        $fontValue = $request->request->get('font');
        $shapeValue = $request->request->get('shape');

        try {
            $this->buttonManager->updateFont($fontValue);
            $this->buttonManager->updateShape($shapeValue);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse([
                'error' => 'bad_request',
                'reason' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
