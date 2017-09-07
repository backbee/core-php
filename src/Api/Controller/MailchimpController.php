<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

use DrewM\MailChimp\MailChimp;

use BackBeePlanet\GlobalSettings;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class MailchimpController extends AbstractController
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->request = $app->getRequest();
    }

    public function getInformation()
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $mailchimpConfig = (new GlobalSettings())->mailchimp();

        return new JsonResponse([
            'client_id'    => $mailchimpConfig['client_id'],
            'redirect_url' => $mailchimpConfig['redirect_url'],
            'origin'       => $mailchimpConfig['origin'],
        ]);
    }

    public function getToken($code)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $mailchimpConfig = (new GlobalSettings())->mailchimp();

        $client = new Client();

        try {
            $res = $client->request('POST', 'https://login.mailchimp.com/oauth2/token', [
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => $mailchimpConfig['client_id'],
                    'client_secret' => $mailchimpConfig['client_secret'],
                    'redirect_uri'  => $mailchimpConfig['redirect_url'],
                    'code'          => $code,
                ]
            ]);
        } catch (\Exception $e) {
            return Response::create('', 400);
        }

        $tokenData = json_decode($res->getBody(), true);
        try {
            $resDataCenter = $client->request('GET', 'https://login.mailchimp.com/oauth2/metadata', [
                'headers' => [
                    'User-Agent'    => 'oauth2-draft-v10',
                    'Accept'        => 'application/json',
                    'Authorization' => 'OAuth ' . $tokenData['access_token']
                ]
            ]);
        } catch (\Exception $e) {
            return Response::create('', 400);
        }

        $dataCenterData = json_decode($resDataCenter->getBody(), true);

        return new JsonResponse([
            'token'        => $tokenData['access_token'],
            'dc'           => $dataCenterData['dc'],
            'account_name' => $dataCenterData['accountname'],
            'api_endpoint' => $dataCenterData['api_endpoint'],
        ]);
    }

    public function getLists($token)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $dc = $this->request->get('dc', null);

        if (null === $dc) {
            return Response::create('', 400);
        }

        $mailchimp = new MailChimp($token . '-' . $dc);

        $lists = $mailchimp->get('lists');

        return new JsonResponse($lists['lists']);
    }
}
