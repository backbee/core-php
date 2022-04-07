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

use BackBee\BBApplication;
use DrewM\MailChimp\MailChimp;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class MailchimpController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class MailchimpController extends AbstractController
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var BBApplication
     */
    private $bbApp;

    /**
     * MailchimpController constructor.
     *
     * @param BBApplication $bbApp
     */
    public function __construct(BBApplication $bbApp)
    {
        parent::__construct($bbApp);

        $this->bbApp = $bbApp;
        $this->request = $bbApp->getRequest();
    }

    /**
     * Get information.
     *
     * @return JsonResponse
     */
    public function getInformation(): JsonResponse
    {
        $this->assertIsAuthenticated();

        $mailchimpConfig = $this->bbApp->getConfig()->getSection('mailchimp');

        return new JsonResponse(
            [
                'client_id' => $mailchimpConfig['client_id'],
                'redirect_url' => $mailchimpConfig['redirect_url'],
                'origin' => $mailchimpConfig['origin'],
            ]
        );
    }

    /**
     * Get token.
     *
     * @param $code
     *
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function getToken($code): JsonResponse
    {
        $this->assertIsAuthenticated();

        $mailchimpConfig = $this->bbApp->getConfig()->getSection('mailchimp');
        $client = new Client();
        $response = null;

        try {
            $res = $client->request(
                'POST',
                'https://login.mailchimp.com/oauth2/token',
                [
                    'form_params' => [
                        'grant_type' => 'authorization_code',
                        'client_id' => $mailchimpConfig['client_id'],
                        'client_secret' => $mailchimpConfig['client_secret'],
                        'redirect_uri' => $mailchimpConfig['redirect_url'],
                        'code' => $code,
                    ],
                ]
            );
        } catch (Exception $exception) {
            return new JsonResponse($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $tokenData = json_decode($res->getBody(), true);

        try {
            $resDataCenter = $client->request(
                'GET',
                'https://login.mailchimp.com/oauth2/metadata',
                [
                    'headers' => [
                        'User-Agent' => 'oauth2-draft-v10',
                        'Accept' => 'application/json',
                        'Authorization' => 'OAuth ' . $tokenData['access_token'],
                    ],
                ]
            );
            $dataCenterData = json_decode($resDataCenter->getBody(), true);
            $response = new JsonResponse(
                [
                    'token' => $tokenData['access_token'],
                    'dc' => $dataCenterData['dc'],
                    'account_name' => $dataCenterData['accountname'],
                    'api_endpoint' => $dataCenterData['api_endpoint'],
                ]
            );
        } catch (Exception $exception) {
            $response = new JsonResponse($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $response;
    }

    /**
     * Get list.
     *
     * @param $token
     *
     * @return JsonResponse
     */
    public function getLists($token): JsonResponse
    {
        $this->assertIsAuthenticated();

        $response = null;
        $dc = $this->request->get('dc');

        if (null === $dc) {
            $response = new JsonResponse('dc parameter is null', Response::HTTP_BAD_REQUEST);
        } else {
            try {
                $mailchimp = new MailChimp($token . '-' . $dc);
                $lists = $mailchimp->get('lists');
                $response = new JsonResponse($lists['lists']);
            } catch (Exception $exception) {
                $response = new JsonResponse($exception->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        return $response;
    }
}
