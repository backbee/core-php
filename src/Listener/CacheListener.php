<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\UserAgentHelper;
use BackBeePlanet\Redis\RedisManager;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Renderer\Event\RendererEvent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CacheListener
{
    const COOKIE_DISABLE_CACHE = 'DISABLECACHE';

    protected static $redisClient;

    /**
     * Called on `rest.controller.securitycontroller.authenticateaction.postcall` event.
     *
     * @param  RendererEvent  $event
     */
    public static function onAuthenticationPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            return;
        }

        self::setCacheCookie($response);
    }

    public static function onGetCategoryPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return;
        }

        self::setCacheCookie($response);
    }

    public static function onLogoutPostCall(PostResponseEvent $event)
    {
        $event->getResponse()->headers->clearCookie(self::COOKIE_DISABLE_CACHE);
    }

    public static function onKernelResponse(FilterResponseEvent $event)
    {
        $app = $event->getKernel()->getApplication();
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (
            $response instanceof JsonResponse
            || Response::HTTP_OK !== $response->getStatusCode()
            || !$request->isMethod('get')
            || 'default' !== $request->attributes->get('_route')
            || null !== $app->getBBUserToken()
        ) {
            return;
        }

        if (null === $redisClient = RedisManager::getClient()) {
            return;
        }

        $redisClient->set(
            $key = sprintf(
                '%s:%s[%s]',
                $app->getSite()->getLabel(), $request->getRequestUri(),
                UserAgentHelper::getDeviceType()
            ),
            $response->getContent()
        );
        $redisClient->expire($key, 60 * 60 * 24);
    }

    public static function onPublishPostCall(PostResponseEvent $event)
    {
        if (Response::HTTP_NO_CONTENT !== $event->getResponse()->getStatusCode()) {
            return;
        }

        if (0 === $event->getResponse()->headers->get('x-published-count')) {
            return;
        }

        RedisManager::removePageCache($event->getApplication()->getSite()->getLabel());
    }

    public static function onPublishAllPostCall(PostResponseEvent $event)
    {
        if (Response::HTTP_NO_CONTENT !== $event->getResponse()->getStatusCode()) {
            return;
        }

        if (0 === $event->getResponse()->headers->get('x-published-page-count')) {
            return;
        }

        RedisManager::removePageCache($event->getApplication()->getSite()->getLabel());
    }

    public static function onChangePostCall(PostResponseEvent $event)
    {
        if (Response::HTTP_NO_CONTENT !== $event->getResponse()->getStatusCode()) {
            return;
        }

        RedisManager::removePageCache($event->getApplication()->getSite()->getLabel());
    }

    protected static function setCacheCookie(Response $response)
    {
        $response->headers->setCookie(new Cookie(self::COOKIE_DISABLE_CACHE, '1', time() + (60 * 30)));
    }

}
