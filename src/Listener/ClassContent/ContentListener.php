<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBeeCloud\Entity\ContentDuplicatePreSaveEvent;
use BackBeePlanet\GlobalSettings;
use BackBee\ClassContent\AbstractContent;
use BackBee\ClassContent\Basic\Title;
use BackBee\ClassContent\Media\Image;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Event\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentListener
{
    private static $imgCdnHost;

    /**
     * Occurs on `basic.slider.prepersist` to ensure that slider newly created is empty.
     *
     * @param  Event  $event
     */
    public static function onSliderPrePersist(Event $event)
    {
        $event->getTarget()->images = [];
    }

    public static function onContentDuplicatePreSave(ContentDuplicatePreSaveEvent $event)
    {
        $dic = $event->getApplication()->getContainer();
        $content = $event->getContent();

        if ($content instanceof Image && false != $content->path) {
            $mediaDir = $event->getApplication()->getMediaDir();
            if (1 === preg_match('~^/img/[a-f0-9]{32}\.~', (string) $content->path)) {
                $sourcepath = str_replace('/img/', $mediaDir . '/', $content->path);
                if (is_readable($sourcepath)) {
                    $content->path = $dic->get('cloud.file_handler')->upload(
                        sprintf('%s.%s', $content->getUid(), explode('.', basename($sourcepath))[1]),
                        $sourcepath,
                        false
                    );
                }
            }
        } elseif ($content instanceof Title) {
            if (null !== $page = $dic->get('cloud.page_manager')->getCurrentPage()) {
                $content->value = $page->getTitle();
            }
        }
    }

    protected static function getImageCdnHost()
    {
        if (null === self::$imgCdnHost) {
            self::$imgCdnHost = (new GlobalSettings())->cdn()['image_domain'];
        }

        return self::$imgCdnHost;
    }
}
