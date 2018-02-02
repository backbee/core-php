<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\SiteStatusManager;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SiteController
{
    /**
     * @var SiteStatusManager
     */
    private $siteStatusMgr;

    public function __construct(SiteStatusManager $siteStatusMgr)
    {
        $this->siteStatusMgr = $siteStatusMgr;
    }

    public function getWorkProgress()
    {
        $percent = null;

        try {
            $percent = $this->siteStatusMgr->getLockProgress();
        } catch (\LogicException $e) {
            // nothing to do
        }

        return new JsonResponse([
            'work_progression' => $percent,
        ]);
    }
}
