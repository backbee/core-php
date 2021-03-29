<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBeeCloud\Design\FontManager;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class DesignFontController extends AbstractController
{
    /**
     * @var FontManager
     */
    protected $fontManager;

    public function __construct(FontManager $fontManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->fontManager = $fontManager;
    }

    public function getAllAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->fontManager->all());
    }
}
