<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class SoundcloudListener
{
    /**
     * Soundcloud onRender event
     *
     * @param RendererEvent $event
     * @return void
     */
    public static function onRender(RendererEvent $event)
    {
        $renderer = $event->getRenderer();
        $content = $event->getTarget();

        $url = $content->getParamValue('url');

        if (false == $url) {
            return;
        }

        $urlData = parse_url($url);

        $renderer->assign('valid_url', $urlData['host'] === 'soundcloud.com' && $urlData['path'] != false);
    }
}