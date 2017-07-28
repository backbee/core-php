<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBeeCloud\Entity\ContentDuplicatePreSaveEvent;
use BackBeeCloud\UserAgentHelper;
use BackBeePlanet\GlobalSettings;
use BackBee\ClassContent\AbstractContent;
use BackBee\ClassContent\Basic\Title;
use BackBee\ClassContent\Content\HighlightContent;
use BackBee\ClassContent\Media\Image;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;

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
        } elseif ($content instanceof HighlightContent) {
            $data = $content->getParamValue('content');
            if (false == $data) {
                return;
            }

            $currentPage = $dic->get('cloud.page_manager')->getCurrentPage();
            if (null === $currentPage) {
                $content->setParam('content', []);

                return;
            }

            $results = [];
            $langMgr = $dic->get('multilang_manager');
            $currentLang = $langMgr->getLangByPage($currentPage);
            foreach ($content->getParamValue('content') as $row) {
                if (!isset($row['id'])) {
                    continue;
                }

                $page = $dic->get('em')->find(Page::class, $row['id']);
                if ($page && $currentLang !== $langMgr->getLangByPage($page)) {
                    continue;
                }

                $results[] = $row;
            }

            $content->setParam('content', $results);
        }
    }

    public static function onCloudContentSetRender(RendererEvent $event)
    {
        if (UserAgentHelper::isDesktop()) {
            return;
        }

        $content = $event->getTarget();
        $param = $content->getParamValue('responsive_' . UserAgentHelper::getDeviceType());
        if (isset($param['nb_item_max']) && 0 == $param['nb_item_max']) {
            $event->getRenderer()->assign('hide_content', true);
            $event->stopPropagation();
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
