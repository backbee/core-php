<?php

namespace BackBeeCloud\Controller;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use LpDigital\Bundle\HAuthBundle\Config\Configurator;
use LpDigital\Bundle\HAuthBundle\Entity\UserProfile;
use LpDigital\Bundle\HAuthBundle\Listener\Event\HAuthEvent;

use BackBee\BBApplication;
use BackBee\Security\Exception\SecurityException;
use BackBee\Security\Listeners\PublicKeyAuthenticationListener;
use BackBee\Security\Token\BBUserToken;
use BackBee\Security\Token\PublicKeyToken;
use BackBee\Security\User;

use BackBeeCloud\Listener\CacheListener;

/**
 * HAuthController
 *
 * @copyright    ©2017 - Lp digital
 * @author       Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class HAuthController
{

    private $application;

    /**
     * Controller constructor.
     *
     * @param BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        $this->application = $application;
    }

    /**
     * Opens a authenticated session on REST API.
     *
     * @param  Request $request
     *
     * @return Response
     */
    public function authenticateAction(Request $request)
    {
        if (!$this->application->getContainer()->has('hauth.rest_api_area.listener')) {
            return new Response('Server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (null !== $publicKey = $request->headers->get(PublicKeyAuthenticationListener::AUTH_PUBLIC_KEY_TOKEN)) {
            return $this->createBBUsetToken($request, $publicKey);
        }

        $login = $request->get('login', '');
        $key = $request->get('key', '');
        $user = $this->application->getEntityManager()->getRepository(User::class)->findOneBy(['_login' => $login, '_api_key_private' => $key]);
        if (null === $user) {
            return new Response('User not found', Response::HTTP_NOT_FOUND);
        }

        $profile = new UserProfile([
            'network' => $request->get('network', ''),
            'identifier' => $request->get('identifier', '')
        ]);
        $this->application->getSecurityContext()->setToken(null);
        $event = new HAuthEvent($profile, new Response(), Configurator::$apiFirewallId);

        $this->application->getContainer()->get('hauth.rest_api_area.listener')->handle($event);

        return $event->getResponse();
    }

    private function createBBUsetToken(Request $request, $publicKey)
    {
        $response = new Response();
        $signature = $request->headers->get(PublicKeyAuthenticationListener::AUTH_SIGNATURE_TOKEN);

        $apiToken = new PublicKeyToken();
        $apiToken->setUser($publicKey)
                ->setPublicKey($publicKey)
                ->setNonce($signature);

        try {
            $token = $this->application
                    ->getSecurityContext()
                    ->getAuthenticationManager()
                    ->authenticate($apiToken);

            $bbToken = new BBUserToken(['ROLE_API_USER']);
            $bbToken->setUser($token->getUser())
                    ->setCreated($token->getCreated());

            $this->application
                    ->getSecurityContext()
                    ->setToken($bbToken);

            $session = $request->getSession();
            $session->set('_security_front_area', serialize($bbToken));

            $cookie = new Cookie(CacheListener::COOKIE_DISABLE_CACHE, '1');
            $response->headers->setCookie($cookie);
        } catch (SecurityException $e) {
            $response->setContent($e->getMessage());
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}
