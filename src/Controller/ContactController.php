<?php

namespace BackBeeCloud\Controller;

use BackBee\BBApplication;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ContactController
 *
 * @package BackBeeCloud\Controller
 *
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
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

        $transport = Swift_SmtpTransport::newInstance(
            $mailerConfig['server'],
            $mailerConfig['port'],
            $mailerConfig['encryption']
        );
        $transport->setUsername($mailerConfig['username'])->setPassword($mailerConfig['password']);

        Swift_Mailer::newInstance($transport)->send($message);

        return Response::create('ok');
    }
}