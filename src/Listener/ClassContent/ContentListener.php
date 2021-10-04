<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

use BackBee\ClassContent\Basic\Title;
use BackBee\ClassContent\Content\HighlightContent;
use BackBee\ClassContent\Element\File;
use BackBee\Event\Event;
use BackBee\HttpClient\UserAgent;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use BackBeeCloud\Entity\ContentDuplicatePreSaveEvent;
use Exception;

/**
 * Class ContentListener
 *
 * @package BackBeeCloud\Listener\ClassContent
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentListener
{
    public const YOUTUBE_URL_REGEX = '(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'>]+)(&start=([0-9]+))?(&end=([0-9]+))?';
    public const YOUTUBE_EMBED_BASE_URL = 'https://www.youtube.com/embed/';

    /**
     * Occurs on `basic.slider.prepersist` to ensure that slider newly created is empty.
     *
     * @param Event $event
     */
    public static function onSliderPrePersist(Event $event): void
    {
        $event->getTarget()->images = [];
    }

    /**
     * On content duplicate pre save.
     *
     * @param ContentDuplicatePreSaveEvent $event
     */
    public static function onContentDuplicatePreSave(ContentDuplicatePreSaveEvent $event): void
    {
        $dic = $event->getApplication()->getContainer();
        $content = $event->getContent();

        if ($content instanceof File && false !== $content->path) {
            if (false !== strpos($content->path, 'theme-default-resources')) {
                return;
            }

            try {
                $content->path = $event->getApplication()->getContainer()->get('cloud.file_handler')->duplicate(
                    $content->path,
                    sprintf('%s.%s', $content->getUid(), explode('.', basename($content->path))[1])
                ) ?? '';
            } catch (Exception $exception) {
                $content->path = null;
            }
        } elseif ($content instanceof Title) {
            if (null !== $page = $dic->get('cloud.page_manager')->getCurrentPage()) {
                $content->value = $page->getTitle();
            }
        } elseif ($content instanceof HighlightContent) {
            $data = $content->getParamValue('content');
            if (false === $data) {
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

    /**
     * On cloud content set render.
     *
     * @param RendererEvent $event
     */
    public static function onCloudContentSetRender(RendererEvent $event): void
    {
        $content = $event->getTarget();
        if (false !== $bgVideoUrl = $content->getParamValue('bg_video')) {
            $videoId = null;
            $start = false;
            $end = false;
            if (1 === preg_match('~' . self::YOUTUBE_URL_REGEX . '~', $bgVideoUrl, $matches)) {
                $videoId = $matches[5];
                if (isset($matches[7])) {
                    $start = $matches[7];
                }

                if (isset($matches[9])) {
                    $end = $matches[9];
                }
            }

            if ($videoId) {
                $event->getRenderer()->assign('bg_video_id', $videoId);
                $event->getRenderer()->assign('bg_video_start_at', $start);
                $event->getRenderer()->assign('bg_video_end_at', $end);
                $event->getRenderer()->assign(
                    'bg_video_url',
                    self::YOUTUBE_EMBED_BASE_URL . $videoId . '?' . http_build_query(
                        [
                            'autohide' => 0,
                            'autoplay' => 1,
                            'cc_load_policy' => 0,
                            'controls' => 0,
                            'iv_load_policy' => 3,
                            'loop' => 1,
                            'mute' => 1,
                            'playlist' => $videoId,
                            'showinfo' => 0,
                        ]
                    )
                );
            }
        }

        $content->setParam(
            'bg_image',
            $event->getRenderer()->getCdnImageUrl(
                $event->getRenderer()->getOptimizeImagePathHelper(
                    $content->getParamValue('bg_image'),
                    false,
                    12
                )
            )
        );

        if (UserAgent::isDesktop()) {
            return;
        }

        $param = $content->getParamValue('responsive_' . UserAgent::getDeviceType());
        if (isset($param['nb_item_max']) && 0 === $param['nb_item_max']) {
            $event->getRenderer()->assign('hide_content', true);
            $event->stopPropagation();
        }
    }
}
