<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CardListener
{
    public static function onRender(RendererEvent $event)
    {
        $card = $event->getTarget();
        $card->image->setParam('stretch', $card->getParamValue('image_stretch'));
    }
}
