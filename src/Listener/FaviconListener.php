<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\UserPreference\UserPreferenceManager;
use BackBee\Renderer\Renderer;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class FaviconListener
{
    const FAVICON_LOCATION_TAG = '<!--##FAVICON_SPOT##-->';

    /**
     * @var UserPreferenceManager
     */
    protected $userPreferenceManager;

    /**
     * @var Renderer
     */
    protected $renderer;

    public function __construct(UserPreferenceManager $userPreferenceManager, Renderer $renderer)
    {
        $this->userPreferenceManager = $userPreferenceManager;
        $this->renderer = $renderer;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();
        if ($response instanceof JsonResponse) {
            return;
        }

        $response = $event->getResponse();
        if (false === strpos($response->getContent(), self::FAVICON_LOCATION_TAG)) {
            return;
        }

        if ($faviconData = $this->userPreferenceManager->dataOf('favicon')) {
            $response->setContent(
                str_replace(
                    self::FAVICON_LOCATION_TAG,
                    $this->renderer->partial(
                        'common/_favicon_part.html.twig',
                        array_map(function ($url) {
                            return str_replace(['http:', 'https:'], '', $url);
                        }, $faviconData)
                    ),
                    $response->getContent()
                )
            );
        }
    }
}
