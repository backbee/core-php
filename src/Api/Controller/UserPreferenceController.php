<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

use BackBee\BBApplication;
use BackBeeCloud\UserPreference\UserPreferenceManager;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserPreferenceController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class UserPreferenceController extends AbstractController
{
    /**
     * @var UserPreferenceManager
     */
    protected $usrPrefMgr;

    /**
     * @var Request
     */
    protected $request;

    /**
     * UserPreferenceController constructor.
     *
     * @param BBApplication $app
     */
    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->usrPrefMgr = $app->getContainer()->get('user_preference.manager');
        $this->request = $app->getRequest();
    }

    /**
     * Get collection.
     *
     * @return JsonResponse
     */
    public function getCollection(): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->usrPrefMgr->all());
    }

    /**
     * GET method.
     *
     * @param $name
     *
     * @return JsonResponse
     */
    public function get($name): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->usrPrefMgr->dataOf($name));
    }

    /**
     * PUT method.
     *
     * @param $name
     *
     * @return JsonResponse|Response
     */
    public function put($name)
    {
        $this->assertIsAuthenticated();

        try {
            $this->usrPrefMgr->setDataOf($name, $this->request->request->all());
        } catch (Exception $ex) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => preg_replace('~^\[[a-zA-Z:\\\]+\] ~', '', $ex->getMessage()),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete method.
     *
     * @param $name
     *
     * @return Response
     */
    public function delete($name): Response
    {
        $this->assertIsAuthenticated();

        try {
            $this->usrPrefMgr->removeDataOf($name);
        } catch (Exception $ex) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => preg_replace('~^\[[a-zA-Z:\\\]+\] ~', '', $ex->getMessage()),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
