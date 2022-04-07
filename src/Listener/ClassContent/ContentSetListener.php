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

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
use BackBee\ClassContent\ContentSet;
use BackBee\ClassContent\Revision;
use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentSetListener
{
    public static function onRender(RendererEvent $event)
    {
        $block = $event->getTarget();
        if (ContentSet::class !== get_class($block)) {
            $event->stopPropagation();

            return;
        }

        $bbtoken = $event->getApplication()->getBBUserToken();
        $entityManager = $event->getApplication()->getEntityManager();
        $contents = [];
        foreach ($block->getData() as $content) {
            if (!($content instanceof CloudContentSet)) {
                continue;
            }

            $counter = 0;
            foreach ($content->getData() as $subcontent) {
                if (!($subcontent instanceof ColContentSet)) {
                    continue;
                }

                $counter = $counter + count(array_filter($subcontent->getData()));
            }

            if (0 < $counter) {
                $contents[] = $content;
            } else {
                $entityManager->getRepository(AbstractClassContent::class)->deleteContent($content, true);
                $entityManager->flush();
            }
        }

        $event->getRenderer()->assign('contents', $contents);
        $event->stopPropagation();
    }
}
