<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Design\FontManager;
use BackBee\BBApplication;
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
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        return new JsonResponse($this->fontManager->all());
    }
}
