<?php

namespace BackBeeCloud;

use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class VideoHelper
{
    const YOUTUBE_URL_REGEX = '/(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'>]+)/';
    const YOUTUBE_EMBED_BASE_URL = 'https://www.youtube.com/embed/';

    const DAILYMOTION_URL_REGEX = '/(?:dailymotion\.com\/|dai\.ly)(?:video|hub)?\/([0-9a-z]+)/';

    const VIMEO_URL_REGEX = '/(?:vimeo\.com\/)(?:channels\/[A-z]+\/)?([0-9]+)/';

    public static function isSupportedUrl($url)
    {
        return
            1 === preg_match(self::YOUTUBE_URL_REGEX, $url)
            || 1 === preg_match(self::VIMEO_URL_REGEX, $url)
            || 1 === preg_match(self::DAILYMOTION_URL_REGEX, $url)
        ;
    }

    public static function getVideoThumbnailUrl($url)
    {
        if (!self::isSupportedUrl($url)) {
            return;
        }

        $client = new Client();
        if (1 === preg_match(self::YOUTUBE_URL_REGEX, $url, $matches)) {
            $thumbnailUrl = sprintf('https://img.youtube.com/vi/%s/maxresdefault.jpg', $matches[5]);

            try {
                $client->request('head', $thumbnailUrl);
            } catch (\Exception $e) {
                $thumbnailUrl = sprintf('https://img.youtube.com/vi/%s/0.jpg', $matches[5]);
            }

            return $thumbnailUrl;
        }

        if (1 === preg_match(self::VIMEO_URL_REGEX, $url, $matches)) {
            $response = $client->request('get', sprintf(
                'https://vimeo.com/api/v2/video/%s.json',
                $matches[1]
            ));

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                return;
            }

            $data = json_decode((string) $response->getBody(), true);
            $data = array_pop($data);

            return $data['thumbnail_large'];
        }

        if (1 === preg_match(self::DAILYMOTION_URL_REGEX, $url, $matches)) {
            $response = $client->request('get', sprintf(
                'https://api.dailymotion.com/video/%s?fields=id,thumbnail_720_url',
                $matches[1]
            ));

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                return;
            }

            $data = json_decode((string) $response->getBody(), true);

            return $data['thumbnail_720_url'];
        }
    }
}
