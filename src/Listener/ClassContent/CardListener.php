<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

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
