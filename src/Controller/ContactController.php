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

namespace BackBeeCloud\Controller;

use BackBee\BBApplication;
use Swift_Message;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ContactController
 *
 * @package BackBeeCloud\Controller
 *
 * @author  Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class ContactController
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * ContactController constructor.
     *
     * @param BBApplication $app
     */
    public function __construct(BBApplication $app)
    {
        $this->app = $app;
    }

    /**
     * Send
     *
     * @return Response
     */
    public function send(): Response
    {
        $request = $this->app->getRequest();
        $mailerConfig = $this->app->getConfig()->getSection('mailer');

        $destEmail = explode(';', $request->get('dest_email'));
        array_walk(
            $destEmail,
            static function (&$item) {
                $item = trim($item);
            }
        );

        $email = $request->get('email');
        $message = $request->get('message');

        $message = Swift_Message::newInstance()
            ->setSubject('New message from ' . $email)
            ->setFrom($mailerConfig['email_from'])
            ->setTo($destEmail)
            ->setBody($message, 'text/html');

        $this->app->getContainer()->get('mailer')->send($message);

        return Response::create('ok');
    }
}
