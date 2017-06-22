<?php

namespace BackBeeCloud\Controller;

use BackBee\BBApplication;
use BackBeePlanet\GlobalSettings;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class ContactController
{
    /**
     * @var \BackBee\BBApplication
     */
    protected $app;

    public function __construct(BBApplication $app)
    {
        $this->app = $app;
    }

    public function send()
    {
        $request = $this->app->getRequest();
        $mailerConfig = (new GlobalSettings())->mailer();

        $name = $request->get('name');
        $destEmail = explode(';', $request->get('dest_email'));
        array_walk($destEmail, function (&$item) { $item = trim($item); });

        $email = $request->get('email');
        $message = $request->get('message');

        $message = \Swift_Message::newInstance()
            ->setSubject('New message from ' . $email)
            ->setFrom($mailerConfig['email_from'])
            ->setTo($destEmail)
            ->setBody($message, 'text/html');

        $transport = \Swift_SmtpTransport::newInstance($mailerConfig['server'], $mailerConfig['port'], $mailerConfig['encryption']);
        $transport->setUsername($mailerConfig['username'])->setPassword($mailerConfig['password']);

        \Swift_Mailer::newInstance($transport)->send($message);

        return Response::create('ok');
    }
}