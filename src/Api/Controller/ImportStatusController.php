<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

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
