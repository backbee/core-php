<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\ThemeColor\ThemeColorManager;
use BackBeeCloud\ThemeColor\ColorPanelManager;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ThemeColorController extends AbstractController
{
    /**
     * @var ThemeColorManager
     */
    protected $themeColorManager;

    /**
     * @var ColorPanelManager
     */
    protected $colorPanelManager;

    public function __construct(ThemeColorManager $themeColorManager, ColorPanelManager $colorPanelManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->themeColorManager = $themeColorManager;
        $this->colorPanelManager = $colorPanelManager;
    }

    public function getAllThemesAction()
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        return new JsonResponse($this->themeColorManager->all());
    }

    public function getCurrentThemeAction()
    {
        return new JsonResponse(
            $this->themeColorManager->getByColorPanel($this->colorPanelManager->getColorPanel())
        );
    }
}
