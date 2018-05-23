<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBeeCloud\Entity\ContentDuplicatePreSaveEvent;
use BackBeeCloud\UserAgentHelper;
use BackBeePlanet\GlobalSettings;
use BackBee\ClassContent\Basic\Title;
use BackBee\ClassContent\Content\HighlightContent;
use BackBee\ClassContent\Element\File;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentListener
{
    const YOUTUBE_URL_REGEX = '(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'>]+)';
    const YOUTUBE_EMBED_BASE_URL = 'https://www.youtube.com/embed/';

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

        if ($content instanceof File && false != $content->path) {
            $content->path = $event->getApplication()->getContainer()->get('cloud.file_handler')->duplicate(
                $content->path,
                sprintf('%s.%s', $content->getUid(), explode('.', basename($content->path))[1])
            );
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
        $content = $event->getTarget();
        if (false != $bgVideoUrl = $content->getParamValue('bg_video')) {
            $videoId = null;
            if (1 === preg_match('~' . self::YOUTUBE_URL_REGEX . '~', $bgVideoUrl, $matches)) {
                $videoId = $matches[5];
            }

            if ($videoId) {
                $event->getRenderer()->assign('bg_video_id', $videoId);
                $event->getRenderer()->assign(
                    'bg_video_url',
                    self::YOUTUBE_EMBED_BASE_URL . $videoId . '?' . http_build_query([
                        'autohide'       => 0,
                        'autoplay'       => 1,
                        'cc_load_policy' => 0,
                        'controls'       => 0,
                        'iv_load_policy' => 3,
                        'loop'           => 1,
                        'mute'           => 1,
                        'playlist'       => $videoId,
                        'showinfo'       => 0,
                    ])
                );
            }
        }

        if (UserAgentHelper::isDesktop()) {
            return;
        }

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
