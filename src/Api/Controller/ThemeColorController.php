<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Security\UserRightConstants;
use BackBeeCloud\ThemeColor\ColorPanelManager;
use BackBeeCloud\ThemeColor\ThemeColorManager;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $this->assertIsAuthenticated();

        return new JsonResponse($this->themeColorManager->all());
    }

    public function getCurrentThemeAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $this->themeColorManager->getByColorPanel($this->colorPanelManager->getColorPanel())
        );
    }
}
