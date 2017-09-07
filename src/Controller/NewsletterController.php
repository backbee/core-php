<?php

namespace BackBeeCloud\Controller;

use BackBeePlanet\GlobalSettings;
use BackBee\ClassContent\Basic\Newsletter;
use Doctrine\ORM\EntityManager;
use DrewM\MailChimp\MailChimp;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class NewsletterController
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entyMgr;

    public function __construct(EntityManager $entyMgr)
    {
        $this->entyMgr = $entyMgr;
    }

    public function send(Request $request)
    {
        $email = $request->request->get('email', null);
        if (false == $email) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $contentUid = $request->request->get('content_uid', null);
        if (false == $contentUid) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $content = $this->entyMgr->find(Newsletter::class, $contentUid);
        if (null === $content) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $params = $content->getParamValue('connector');
        if (false == $params) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $mailchimpConfig = $params['mailchimp'];
        if (false == $mailchimpConfig) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $mailchimp = new MailChimp($mailchimpConfig['token'] . '-' . $mailchimpConfig['dc']);
        $mailchimp->post('lists/' . $mailchimpConfig['current_list'] . '/members', [
            'email_address' => $email,
            'status'        => 'subscribed',
        ]);

        return new Response('', Response::HTTP_CREATED);
    }
}
