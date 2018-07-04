<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Media\Map;
use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class AutoHeightContentListener
{
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
                $colContentSet instanceof ColContentSet
                && 1 === count($colContentSet->getData())
                && self::isContentAutoHeightEnabled($colContentSet->getData()[0])
            ) {
                $currentValue = (string) $event->getRenderer()->row_extra_css_classes;
                $currentValue = $currentValue . ' auto-height';
                $event->getRenderer()->assign('row_extra_css_classes', $currentValue);

                return;
            }
        }
    }

    protected static function isContentAutoHeightEnabled(AbstractClassContent $content)
    {
        $paramKeys = array_keys($content->getAllParams());

        return
            ($content instanceof Image || $content instanceof Map)
            && in_array('force_auto_height', $paramKeys)
            && true === $content->getParamValue('force_auto_height')
        ;
    }
}
