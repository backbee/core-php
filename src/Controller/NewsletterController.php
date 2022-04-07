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

namespace BackBeeCloud\Controller;

use BackBee\BBApplication;
use BackBee\ClassContent\Basic\Newsletter;
use Doctrine\ORM\EntityManager;
use DrewM\MailChimp\MailChimp;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class NewsletterController
 *
 * @package BackBeeCloud\Controller
 *
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class NewsletterController
{
    /**
     * @var BBApplication
     */
    private $bbApp;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * NewsletterController constructor.
     *
     * @param BBApplication $bbApp
     * @param EntityManager $entityManager
     */
    public function __construct(BBApplication $bbApp, EntityManager $entityManager)
    {
        $this->bbApp = $bbApp;
        $this->entityManager = $entityManager;
    }

    /**
     * Send request.
     *
     * @param Request $request
     *
     * @return Response|null
     */
    public function send(Request $request): ?Response
    {
        $response = null;
        $mailchimpConfig = [];
        $email = $request->request->get('email');
        $contentUid = $request->request->get('content_uid');

        if (false === $email || false === $contentUid) {
            $response = new Response('Cannot found email or content_uid parameter', Response::HTTP_BAD_REQUEST);
        }

        try {
            if (
                null === ($content = $this->entityManager->find(Newsletter::class, $contentUid)) ||
                null === ($params = $content->getParamValue('connector')) ||
                null === ($mailchimpConfig = $params['mailchimp'] ?? null)
            ) {
                $response = new Response(
                    sprintf('Cannot found newsletter with uid %s', $contentUid),
                    Response::HTTP_BAD_REQUEST
                );
            }
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        try {
            $mailchimp = new MailChimp($mailchimpConfig['token'] . '-' . $mailchimpConfig['dc']);
            $mailchimp->post(
                'lists/' . $mailchimpConfig['current_list'] . '/members',
                [
                    'email_address' => $email,
                    'status' => 'subscribed',
                ]
            );
            $response = new Response('', Response::HTTP_CREATED);
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__, __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $response;
    }
}
