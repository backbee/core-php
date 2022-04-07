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

namespace BackBee\ClassContent\Media;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class VideoHelper
 *
 * @package BackBee\ClassContent\Media
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class VideoManager
{
    public const YOUTUBE_URL_REGEX = '/(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'>]+)/';
    public const YOUTUBE_EMBED_BASE_URL = 'https://www.youtube.com/embed/';
    public const DAILYMOTION_URL_REGEX = '/(?:dailymotion\.com\/|dai\.ly)(?:video|hub)?\/([0-9a-z]+)/';
    public const VIMEO_URL_REGEX = '/(?:vimeo\.com\/)(?:channels\/[A-z]+\/)?(\d+)(?:\/?[A-Za-z0-9]+)/';
    public const VIMEO_PRIVATE_URL_REGEX = '/(?:vimeo\.com\/)(?:channels\/[A-z]+\/)?(\d+)(\/[A-Za-z0-9]+)/';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Check is supported url.
     *
     * @param $url
     *
     * @return bool
     */
    public function isSupportedUrl($url): bool
    {
        return
            preg_match(self::YOUTUBE_URL_REGEX, $url) === 1
            || preg_match(self::VIMEO_URL_REGEX, $url) === 1
            || preg_match(self::DAILYMOTION_URL_REGEX, $url) === 1;
    }

    /**
     * Get video thumbnail url.
     *
     * @param $url
     *
     * @return string|void
     */
    public function getVideoThumbnailUrl($url)
    {
        if (!$this->isSupportedUrl($url)) {
            return;
        }

        $thumbnail = '';

        $client = new Client();

        if (preg_match(self::YOUTUBE_URL_REGEX, $url, $matches) === 1) {
            $thumbnail = $this->isYoutubeVideo($client, $matches);
        }

        if (preg_match(self::VIMEO_URL_REGEX, $url, $matches) === 1) {
            $thumbnail = $this->isVimeoVideo($client, $matches);
        }

        if (preg_match(self::DAILYMOTION_URL_REGEX, $url, $matches) === 1) {
            $thumbnail = $this->isDailymotionVideo($client, $matches);
        }

        return $thumbnail;
    }

    /**
     * Is Youtube video.
     *
     * @param $client
     * @param $matches
     *
     * @return string
     */
    private function isYoutubeVideo($client, $matches): string
    {
        $thumbnailUrl = sprintf('https://img.youtube.com/vi/%s/maxresdefault.jpg', $matches[5]);

        try {
            $client->request('head', $thumbnailUrl);
        } catch (Exception $exception) {
            $this->logger->warning(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
            $thumbnailUrl = sprintf('https://img.youtube.com/vi/%s/hqdefault.jpg', $matches[5]);
        }

        return $thumbnailUrl;
    }

    /**
     * Is Vimeo video.
     *
     * @param $client
     * @param $matches
     *
     * @return string
     */
    private function isVimeoVideo($client, $matches): string
    {
        try {
            $response = $client->request(
                'get',
                sprintf(
                    'https://vimeo.com/api/oembed.json?url=https://%s',
                    $matches[0]
                )
            );
        } catch (Exception $exception) {
            $this->logger->warning(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
            return '';
        }

        $data = json_decode((string)$response->getBody(), true);

        $data = array_pop($data);

        return $data['thumbnail_large'];
    }

    /**
     * Is Dailymotion video.
     *
     * @param $client
     * @param $matches
     *
     * @return string
     */
    private function isDailymotionVideo($client, $matches): string
    {
        $response = $client->request(
            'get',
            sprintf(
                'https://api.dailymotion.com/video/%s?fields=id,thumbnail_720_url',
                $matches[1]
            )
        );

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return '';
        }

        $data = json_decode((string)$response->getBody(), true);

        return $data['thumbnail_720_url'];
    }

    /**
     * Get mobile video thumbnail url.
     *
     * @param $url
     *
     * @return string|void
     */
    public function getMobileVideoThumbnailUrl($url)
    {
        if (!$this->isSupportedUrl($url)) {
            return;
        }

        $thumbnail = '';

        $client = new Client();

        if (preg_match(self::YOUTUBE_URL_REGEX, $url, $matches) === 1) {
            $thumbnail = $this->isMobileYoutubeVideo($client, $matches);
        }

        if (preg_match(self::VIMEO_URL_REGEX, $url, $matches) === 1) {
            $thumbnail = $this->isMobileVimeoVideo($client, $matches);
        }

        if (preg_match(self::DAILYMOTION_URL_REGEX, $url, $matches) === 1) {
            $thumbnail = $this->isMobileDailymotionVideo($client, $matches);
        }

        return $thumbnail;
    }

    /**
     * Is mobile Youtube video.
     *
     * @param $client
     * @param $matches
     *
     * @return string
     */
    private function isMobileYoutubeVideo($client, $matches): string
    {
        $thumbnailUrl = sprintf('https://img.youtube.com/vi/%s/maxresdefault.jpg', $matches[5]);

        try {
            $client->request('head', $thumbnailUrl);
        } catch (Exception $e) {
            $thumbnailUrl = sprintf('https://img.youtube.com/vi/%s/hqdefault.jpg', $matches[5]);
        }

        return $thumbnailUrl;
    }

    /**
     * Is mobile Vimeo video.
     *
     * @param $client
     * @param $matches
     *
     * @return string
     */
    private function isMobileVimeoVideo($client, $matches): string
    {
        $response = $client->request(
            'get',
            sprintf(
                'https://vimeo.com/api/v2/video/%s.json',
                $matches[1]
            )
        );

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return '';
        }

        $data = json_decode((string)$response->getBody(), true);
        $data = array_pop($data);

        return $data['thumbnail_medium'];
    }

    /**
     * Is mobile Dailymotion video.
     *
     * @param $client
     * @param $matches
     *
     * @return string
     */
    private function isMobileDailymotionVideo($client, $matches): string
    {
        $response = $client->request(
            'get',
            sprintf(
                'https://api.dailymotion.com/video/%s?fields=id,thumbnail_480_url',
                $matches[1]
            )
        );

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return '';
        }

        $data = json_decode((string)$response->getBody(), true);

        return $data['thumbnail_480_url'];
    }
}
