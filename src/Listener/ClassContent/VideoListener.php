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

use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Media\Video;
use BackBee\ClassContent\Media\VideoManager;
use BackBee\ClassContent\Revision;
use BackBee\Event\Event;
use BackBee\Renderer\Event\RendererEvent;
use Exception;
use Psr\Log\LoggerInterface;
use const FILTER_VALIDATE_URL;

/**
 * Class VideoListener
 *
 * @package BackBeeCloud\Listener\ClassContent
 *
 * @author  Marian Hodis <marian.hodis@lp-digital.fr>
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class VideoListener
{
    public const YOUTUBE_HOST = 'www.youtube.com';
    public const YOUTUBE_HOST_SHORT = 'youtu.be';
    public const DAILYMOTION_HOST = 'www.dailymotion.com';
    public const VIMEO_HOST = 'vimeo.com';
    public const BFMTV_HOST = 'www.bfmtv.com';

    public const YOUTUBE_BASEURL = '//www.youtube.com/embed/';
    public const VIMEO_BASEURL = '//player.vimeo.com/video/';
    public const DAILYMOTION_BASEURL = '//www.dailymotion.com/embed/video/';
    public const BFMTV_BASEURL = '/static/nxt-video/player.html';

    /**
     * Default video sizes
     *
     * @var array
     */
    private static $defaultVideoSizes = [
        'auto' => 100,
        'md' => 75,
        'xs' => 50,
    ];

    /**
     * @var VideoManager
     */
    private static $videoManager;

    /**
     * @var LoggerInterface
     */
    private static $logger;

    /**
     * VideoListener constructor.
     *
     * @param VideoManager    $videoManager
     * @param LoggerInterface $logger
     */
    public function __construct(VideoManager $videoManager, LoggerInterface $logger)
    {
        self::$videoManager = $videoManager;
        self::$logger = $logger;
    }

    /**
     * On flush video revision.
     *
     * @param Event $event
     */
    public static function onVideoRevisionFlush(Event $event): void
    {
        $app = $event->getApplication();
        if (($bbtoken = $app->getBBUserToken()) === null) {
            return;
        }

        $entityManager = $event->getApplication()->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $revision = $event->getTarget();
        if (
            $unitOfWork->isScheduledForDelete($revision)
            || Revision::STATE_TO_DELETE === $revision->getState()
            || !($revision->getContent() instanceof Video)
        ) {
            return;
        }

        $videoUrl = $revision->getParamValue('video_url');
        if (
            $videoUrl === false
            || filter_var($videoUrl, FILTER_VALIDATE_URL) === false
            || !self::$videoManager->isSupportedUrl($videoUrl)
        ) {
            return;
        }

        $thumbnail = null;
        $content = $revision->getContent();

        try {
            $thumbnail = $entityManager->find(Image::class, $content->thumbnail->getUid());
        } catch (Exception $exception) {
            self::$logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $image = $thumbnail->image;

        $imageDraft = null;
        $isNewDraft = false;

        try {
            $imageDraft = $entityManager->getRepository(Revision::class)->getDraft($image, $bbtoken);
        } catch (Exception $exception) {
            self::$logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        if ($imageDraft === null) {
            $imageDraft = $entityManager->getRepository(Revision::class)->checkout($image, $bbtoken);
            $entityManager->persist($imageDraft);
            $unitOfWork->computeChangeSet(
                $entityManager->getClassMetadata(Revision::class),
                $imageDraft
            );

            $isNewDraft = true;
        }

        $filename = md5($videoUrl);
        if (strpos($imageDraft->path, $filename) !== false) {
            return;
        }

        if (($thumbnailUrl = self::$videoManager->getVideoThumbnailUrl($videoUrl)) === null) {
            return;
        }

        $rawContent = @file_get_contents($thumbnailUrl);
        if ($rawContent === false) {
            return;
        }

        $tmpfilepath = tempnam(sys_get_temp_dir(), '');
        file_put_contents($tmpfilepath, $rawContent);
        $filename .= '.' . pathinfo($thumbnailUrl, PATHINFO_EXTENSION);

        $imageDraft->path = $app->getContainer()->get('cloud.file_handler')->upload(
            $filename,
            $tmpfilepath,
        );

        $method = $unitOfWork->isScheduledForInsert($imageDraft) && !$isNewDraft
            ? 'computeChangeSet'
            : 'recomputeSingleEntityChangeSet';
        $unitOfWork->$method(
            $entityManager->getClassMetadata(Revision::class),
            $imageDraft
        );
    }

    /**
     * Video onRender event
     *
     * @param RendererEvent $event
     *
     * @return void
     */
    public static function onRender(RendererEvent $event): void
    {
        $renderer = $event->getRenderer();
        $content = $event->getTarget();
        $app = $event->getApplication();
        $request = $app->getRequest();
        $userAgent = $request->headers->get('User-Agent');

        $url = $content->getParamValue('video_url');

        if (empty($url)) {
            return;
        }

        $data = self::getData($url);

        $renderer->assign('src', $data['src']);
        $renderer->assign(
            'thumb_url',
            ($renderer->userAgentHelper()->isDesktop() === true) ? self::$videoManager->getVideoThumbnailUrl(
                $url
            ) : self::$videoManager->getMobileVideoThumbnailUrl($url)
        );
        $renderer->assign('attributes', $data['attributes']);
        $renderer->assign('position', $content->getParamValue('position'));
        $renderer->assign('size', self::$defaultVideoSizes[$content->getParamValue('size')]);
        $renderer->assign('is_iframe_lazyload_browser', preg_match('/(Chrome|CriOS)\//i', $userAgent));
    }

    /**
     * Get video data based on the url
     *
     * @param string url
     *
     * @return array
     */
    private static function getData($url): array
    {
        $data = [
            'src' => '',
            'attributes' => '',
        ];

        $urlData = (array)parse_url($url);
        $queryString = [];

        if (isset($urlData['query'])) {
            parse_str($urlData['query'], $queryString);
        }

        if (!isset($urlData['host'])) {
            return $data;
        }

        switch ($urlData['host']) {
            case self::YOUTUBE_HOST:
                if (isset($queryString['v'])) {
                    $data['src'] = self::YOUTUBE_BASEURL . $queryString['v'];
                }
                break;
            case self::YOUTUBE_HOST_SHORT:
                $data['src'] = self::YOUTUBE_BASEURL . substr($urlData['path'], 1);
                break;
            case self::DAILYMOTION_HOST:
                $data['src'] = self::DAILYMOTION_BASEURL . strtok(basename($url), '_');
                break;
            case self::VIMEO_HOST:
                $data['src'] = self::VIMEO_BASEURL . (
                    preg_match(self::$videoManager::VIMEO_PRIVATE_URL_REGEX, $url, $matches) === 1 ?
                        ltrim(preg_replace("~/(?!.*/)~", '?h=', $urlData['path']), '/') : substr($urlData['path'], 1)
                );
                break;
            case self::BFMTV_HOST:
                if ($urlData['path'] === self::BFMTV_BASEURL) {
                    $data['src'] = $url;
                }
                break;
        }

        return $data;
    }
}
