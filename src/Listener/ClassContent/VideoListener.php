<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBeeCloud\VideoHelper;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Media\Video;
use BackBee\ClassContent\Revision;
use BackBee\Event\Event;
use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Marian Hodis <marian.hodis@lp-digital.fr>
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class VideoListener
{
    const YOUTUBE_HOST = 'www.youtube.com';
    const DAILYMOTION_HOST = 'www.dailymotion.com';
    const VIMEO_HOST = 'vimeo.com';
    const BFMTV_HOST = 'www.bfmtv.com';

    const YOUTUBE_BASEURL = '//www.youtube.com/embed/';
    const VIMEO_BASEURL = '//player.vimeo.com/video/';
    const DAILYMOTION_BASEURL = '//www.dailymotion.com/embed/video/';
    const BFMTV_BASEURL = '/static/nxt-video/player.html';

    /**
     * Default video sizes
     *
     * @var array
     */
    private static $defaultVideoSizes = [
        'auto' => 100,
        'md' => 75,
        'xs' => 50
    ];

    public static function onVideoRevisionFlush(Event $event)
    {
        $app = $event->getApplication();
        if (null === $bbtoken = $app->getBBUserToken()) {
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
            false == $videoUrl
            || false == filter_var($videoUrl, \FILTER_VALIDATE_URL)
            || !VideoHelper::isSupportedUrl($videoUrl)
        ) {
            return;
        }

        $content = $revision->getContent();
        $thumbnail = $entityManager->find(Image::class, $content->thumbnail->getUid());
        $image = $thumbnail->image;

        $isNewDraft = false;
        $imageDraft = $entityManager->getRepository(Revision::class)->getDraft($image, $bbtoken);
        if (null === $imageDraft) {
            $imageDraft = $entityManager->getRepository(Revision::class)->checkout($image, $bbtoken);
            $entityManager->persist($imageDraft);
            $unitOfWork->computeChangeSet(
                $entityManager->getClassMetadata(Revision::class),
                $imageDraft
            );

            $isNewDraft = true;
        }

        $filename = md5($videoUrl);
        if (false !== strpos($imageDraft->path, $filename)) {
            return;
        }

        if (null === $thumbnailUrl = VideoHelper::getVideoThumbnailUrl($videoUrl)) {
            return;
        }

        $rawContent = @file_get_contents($thumbnailUrl);
        if (false === $rawContent) {
            return;
        }

        $tmpfilepath = tempnam(sys_get_temp_dir(), '');
        file_put_contents($tmpfilepath, $rawContent);
        $filename .= '.'.pathinfo($thumbnailUrl, PATHINFO_EXTENSION);

        $imageDraft->path = $app->getContainer()->get('cloud.file_handler')->upload(
            $filename,
            $tmpfilepath,
            true
        );

        $method = $unitOfWork->isScheduledForInsert($imageDraft) && !$isNewDraft
            ? 'computeChangeSet'
            : 'recomputeSingleEntityChangeSet'
        ;
        $unitOfWork->$method(
            $entityManager->getClassMetadata(Revision::class),
            $imageDraft
        );
    }

    /**
     * Video onRender event
     *
     * @param RendererEvent $event
     * @return void
     */
    public static function onRender(RendererEvent $event)
    {
        $renderer = $event->getRenderer();
        $content = $event->getTarget();

        $url = $content->getParamValue('video_url');

        if (empty($url)) {
            return;
        }

        $data = self::getData($url);

        $renderer->assign('src', $data['src']);
        $renderer->assign('attributes', $data['attributes']);
        $renderer->assign('position', $content->getParamValue('position'));
        $renderer->assign('size', self::$defaultVideoSizes[$content->getParamValue('size')]);
    }

    /**
     * Get video data based on the url
     *
     * @param string url
     * @return array
     */
    private static function getData($url)
    {
        $data = [
            'src'        => '',
            'attributes' => '',
        ];

        $urlData = parse_url($url);
        $queryString = [];
        if (isset($urlData['query'])) {
            parse_str($urlData['query'], $queryString);
        }

        switch ($urlData['host']) {
            case self::YOUTUBE_HOST:
                if (isset($queryString['v'])) {
                    $data['src'] = self::YOUTUBE_BASEURL . $queryString['v'];
                }

                break;
            case self::DAILYMOTION_HOST:
                $data['src'] = self::DAILYMOTION_BASEURL . strtok(basename($url), '_');
                break;

            case self::VIMEO_HOST:
                $data['src'] = self::VIMEO_BASEURL . substr($urlData['path'], 1);
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
