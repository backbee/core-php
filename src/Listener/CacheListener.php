<?php

namespace BackBeeCloud\Listener;

use BackBee\Cache\RedisManager;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\HttpClient\UserAgent;
use Predis\Client;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class CacheListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class CacheListener
{
    public const COOKIE_DISABLE_CACHE = 'DISABLECACHE';

    /**
     * @var Client
     */
    protected static $redisClient;

    /**
     * @var RedisManager
     */
    private static $redisManager;

    /**
     * CacheListener constructor.
     *
     * @param RedisManager $redisManager
     */
    public function __construct(RedisManager $redisManager)
    {
        self::$redisManager = $redisManager;
    }

    /**
     * Called on `rest.controller.securitycontroller.authenticateaction.postcall` event.
     *
     * @param PostResponseEvent $event
     */
    public static function onAuthenticationPostCall(PostResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            return;
        }

        self::setCacheCookie($response);
    }

    /**
     * On get category post call.
     *
     * @param PostResponseEvent $event
     */
    public static function onGetCategoryPostCall(PostResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return;
        }

        self::setCacheCookie($response);
    }

    /**
     * On logout post call.
     *
     * @param PostResponseEvent $event
     */
    public static function onLogoutPostCall(PostResponseEvent $event): void
    {
        $event->getResponse()->headers->clearCookie(self::COOKIE_DISABLE_CACHE);
    }

    /**
     * On kernel response.
     *
     * @param FilterResponseEvent $event
     */
    public static function onKernelResponse(FilterResponseEvent $event): void
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

        if (null === $redisClient = self::$redisManager->getClient()) {
            return;
        }

        $redisClient->set(
            $key = sprintf(
                '%s:%s[%s]',
                $app->getSite()->getLabel(),
                $request->getRequestUri(),
                UserAgent::getDeviceType()
            ),
            $response->getContent()
        );
        $redisClient->expire($key, 60 * 60 * 24);
    }

    /**
     * On publish post call.
     *
     * @param PostResponseEvent $event
     */
    public static function onPublishPostCall(PostResponseEvent $event): void
    {
        if (Response::HTTP_NO_CONTENT !== $event->getResponse()->getStatusCode()) {
            return;
        }

        if (0 === $event->getResponse()->headers->get('x-published-count')) {
            return;
        }

        self::$redisManager->removePageCache($event->getApplication()->getSite()->getLabel());
    }

    /**
     * On publish all post call.
     *
     * @param PostResponseEvent $event
     */
    public static function onPublishAllPostCall(PostResponseEvent $event): void
    {
        if (Response::HTTP_NO_CONTENT !== $event->getResponse()->getStatusCode()) {
            return;
        }

        if (0 === $event->getResponse()->headers->get('x-published-page-count')) {
            return;
        }

        self::$redisManager->removePageCache($event->getApplication()->getSite()->getLabel());
    }

    /**
     * On change post call.
     *
     * @param PostResponseEvent $event
     */
    public static function onChangePostCall(PostResponseEvent $event): void
    {
        if (Response::HTTP_NO_CONTENT !== $event->getResponse()->getStatusCode()) {
            return;
        }

        self::$redisManager->removePageCache($event->getApplication()->getSite()->getLabel());
    }

    /**
     * Set cache cookie.
     *
     * @param Response $response
     */
    protected static function setCacheCookie(Response $response): void
    {
        $response->headers->setCookie(new Cookie(self::COOKIE_DISABLE_CACHE, '1', time() + (60 * 30)));
    }
}
