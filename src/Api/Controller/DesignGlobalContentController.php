<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBeeCloud\Design\GlobalContentManager;
use BackBeeCloud\Security\UserRightConstants;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class DesignGlobalContentController extends AbstractController
{
    /**
     * @var GlobalContentManager
     */
    protected $globalContentManager;

    public function __construct(GlobalContentManager $globalContentManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->globalContentManager = $globalContentManager;
    }

    public function getGlobalContentSettingsAction()
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::CUSTOM_DESIGN_FEATURE
        );

        return new JsonResponse($this->globalContentManager->getSettings());
    }

    public function updateGlobalContentSettingsAction(Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::CUSTOM_DESIGN_FEATURE
        );

        if (
            !$request->request->has('headerColor')
            || !$request->request->has('hasHeaderMargin')
            || !$request->request->has('footerColor')
            || !$request->request->has('copyrightColor')
        ) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => "'header', 'hasHeaderMargin', 'footer' and 'copyright' parameters are required to update global content.",
                ], Response::HTTP_BAD_REQUEST
            );
        }

        $headerColor = $request->request->get('headerColor');
        $hasHeaderMargin = $request->request->get('hasHeaderMargin');
        $footerColor = $request->request->get('footerColor');
        $copyrightColor = $request->request->get('copyrightColor');

        try {
            $this->globalContentManager->updateHeaderBackgroundColor($headerColor);
            $this->globalContentManager->updateHasHeaderMargin($hasHeaderMargin);
            $this->globalContentManager->updateFooterBackgroundColor($footerColor);
            $this->globalContentManager->updateCopyrightBackgroundColor($copyrightColor);
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
