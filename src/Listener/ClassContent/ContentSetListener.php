<?php

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
