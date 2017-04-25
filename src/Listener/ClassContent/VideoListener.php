<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Marian Hodis <marian.hodis@lp-digital.fr>
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
