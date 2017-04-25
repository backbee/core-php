<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Entity\ImportStatus;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImportStatusController extends AbstractController
{
    /**
     * @var EntityManager
     */
    protected $entyMgr;

    public function __construct(EntityManager $entyMgr)
    {
        $this->entyMgr = $entyMgr;
    }

    public function getCollection()
    {
        return new JsonResponse(
            $all = $this->entyMgr->getRepository(ImportStatus::class)->findAll(),
            Response::HTTP_OK,
            [
                'Accept-Range'  => 'import-status 50',
                'Content-Range' => 0 === count($all) ? '-/-' : '0-' . (count($all) - 1) . '/' . count($all),
            ]
        );
    }
}
