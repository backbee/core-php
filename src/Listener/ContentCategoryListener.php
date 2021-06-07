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

namespace BackBee\Listener;

use App\Helper\StandaloneHelper;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Routing\RouteCollection;
use BackBeePlanet\Standalone\AbstractStandaloneHelper;
use RuntimeException;
use function array_key_exists;
use function count;
use function in_array;

/**
 * Class ContentCategoryListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class ContentCategoryListener
{
    /**
     * Default ordered content categories.
     *
     * @var array
     */
    private static $defaultData = [
        'block_category_basics' => [
            'pos' => 0,
            'contents_order' => [
                'Basic/Title' => 0,
                'Text/Paragraph' => 1,
                'Basic/Image' => 2,
                'Text/Button' => 3,
                'Media/Video' => 4,
                'Basic/Slider' => 5,
                'Basic/ResponsiveSlider' => 6,
                'Basic/Searchbar' => 7,
                'Basic/Cards' => 8,
            ],
        ],
        'block_category_pages' => [
            'pos' => 1,
        ],
        'block_category_social' => [
            'pos' => 2,
        ],
        'block_category_more' => [
            'pos' => 3,
        ],
        'block_category_privacy_policy' => [
            'pos' => 4,
        ],
    ];

    /**
     * If true, only categories in $defaultData will be returned.
     *
     * @var boolean
     */
    protected static $strict;

    /**
     * @var RouteCollection
     */
    protected static $routing;

    /**
     * ContentCategoryListener constructor.
     *
     * @param RouteCollection $routing
     * @param array           $data     An ordered categories spec.
     * @param bool            $override Should the default data overrided? If false (default)
     *                                  $data will be merged with default one.
     * @param bool            $strict   If true, only categories in $defaultData will be returned.
     */
    public function __construct(RouteCollection $routing, array $data = [], bool $override = false, bool $strict = true)
    {
        if (!class_exists(StandaloneHelper::class)) {
            throw new RuntimeException(
                sprintf(
                    'Class %s is needed for %s to work.',
                    StandaloneHelper::class,
                    static::class
                )
            );
        }

        if (!is_subclass_of(StandaloneHelper::class, AbstractStandaloneHelper::class)) {
            throw new RuntimeException(
                sprintf(
                    'Class %s must extend %s abstract class to work.',
                    StandaloneHelper::class,
                    AbstractStandaloneHelper::class
                )
            );
        }

        if (true === $override) {
            self::$defaultData = $data;
        } else {
            self::$defaultData = array_merge(self::$defaultData, $data);
        }

        self::$strict = $strict;
        self::$routing = $routing;
    }

    /**
     * Re-orders categories to set a custom order.
     *
     * @param PostResponseEvent $event
     */
    public function onGetCategoryPostCall(PostResponseEvent $event): void
    {
        $result = [];
        $response = $event->getResponse();
        $decoded = json_decode($response->getContent(), true);
        $defaultData = $this->getCategoriesData();
        foreach ($decoded as $data) {
            // By default the category is pushed at the end of the array
            $pos = count($result) + count($decoded);

            if (array_key_exists($data['id'], $defaultData)) {
                $config = $defaultData[$data['id']];
                if (isset($config['contents_order'])) {
                    $contents = [];
                    foreach ($data['contents'] as $content) {
                        if (array_key_exists($content['type'], $config['contents_order'])) {
                            $contents[$config['contents_order'][$content['type']]] = $content;
                        }
                    }

                    ksort($contents);

                    $data['contents'] = $contents;
                }

                if (isset($config['pos'])) {
                    $pos = (int)$config['pos'];
                }
            } elseif (self::$strict) {
                continue;
            }

            $data['contents'] = $this->filterContents($data['contents']);

            $result[$pos] = $data;
        }

        ksort($result);

        $response->setContent(json_encode($result));
    }

    /**
     * Returns contents categories data.
     *
     * @return array
     */
    protected function getCategoriesData(): array
    {
        return self::$defaultData;
    }

    /**
     * This method is overridable and allows developers to run custom process on
     * content row.
     *
     * {@see ::onGetCategoryPostCall()} at line 45
     *
     * @param array $content
     *
     * @return array
     */
    protected function runCustomProcessOnContent(array $content): array
    {
        $thumbnailUrl = null;
        if (null !== $thumbnailBaseDir = $this->getContentThumbnailBaseDir()) {
            $thumbnailFilepath = $thumbnailBaseDir . '/' . $content['type'] . '.svg';
            if (file_exists($thumbnailFilepath)) {
                $thumbnailUrl = self::$routing->getUri(
                    str_replace(
                        ['//', StandaloneHelper::rootDir() . '/web'],
                        ['/', ''],
                        $thumbnailFilepath
                    )
                );
            }
        }

        $content['thumbnail'] = $thumbnailUrl;

        return $content;
    }

    /**
     * Filters the set of contents.
     *
     * @param array $contents
     *
     * @return array
     */
    protected function filterContents(array $contents): array
    {
        $filteredContents = [];
        $processedTypes = [];
        foreach ($contents as $content) {
            if (!in_array($content['type'], $processedTypes, true)) {
                $filteredContents[] = $this->runCustomProcessOnContent($content);
                $processedTypes[] = $content['type'];
            }
        }

        return $filteredContents;
    }

    /**
     * Gets content thumbnail base directory.
     *
     * @return string
     */
    private function getContentThumbnailBaseDir(): string
    {
        return realpath(StandaloneHelper::rootDir() . '/web/static/img/contents/');
    }
}
