<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\ThemeColor\ThemeColorManager;
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

    public function __construct(ThemeColorManager $themeColorManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->themeColorManager = $themeColorManager;
    }

    public function getAllThemesAction()
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        return new JsonResponse($this->themeColorManager->all());
    }
}
