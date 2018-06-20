<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\ClassContent\Basic\Cards;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
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

    public static function onCloudContentSetRender(RendererEvent $event)
    {
        $content = $event->getTarget();
        if (!($content instanceof CloudContentSet)) {
            return;
        }

        if (1 === count($content->getData())) {
            return;
        }

        foreach ($content->getData() as $colContentSet) {
            if (
                !($colContentSet instanceof ColContentSet)
                || 1 !== count($colContentSet->getData())
                || !($colContentSet->getData()[0] instanceof Cards)
            ) {
                return;
            }
        }

        $event->getRenderer()->assign('row_extra_css_classes', 'stretch-card');
    }
}
