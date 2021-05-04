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

namespace BackBeePlanet\Standalone;

use BackBeeCloud\Listener\ContentCategoryListener as BaseContentCategoryListener;
use BackBee\Routing\RouteCollection;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentCategoryListener extends BaseContentCategoryListener
{
    const STANDALONE_HELPER_CLASSNAME = 'BackBeePlanet\Standalone\StandaloneHelper';

    /**
     * @var RouteCollection
     */
    protected $routing;

    /**
     * Constructor.
     *
     * @param RouteCollection $routing
     */
    public function __construct(array $data = [], $override = false, $strict = true, RouteCollection $routing)
    {
        if (!class_exists(self::STANDALONE_HELPER_CLASSNAME)) {
            throw new \RuntimeException(sprintf(
                'Class %s is needed for %s to work.',
                self::STANDALONE_HELPER_CLASSNAME,
                static::class
            ));
        }

        if (!is_subclass_of(self::STANDALONE_HELPER_CLASSNAME, AbstractStandaloneHelper::class)) {
            throw new \RuntimeException(sprintf(
                'Class %s must extend %s abstract class to work.',
                self::STANDALONE_HELPER_CLASSNAME,
                AbstractStandaloneHelper::class
            ));
        }

        parent::__construct($data, $override, $strict);

        $this->routing = $routing;
    }

    /**
     * {@inheritdoc}
     */
    protected function runCustomProcessOnContent(array $content)
    {
        $thumbnailUrl = null;
        $helperClass = self::STANDALONE_HELPER_CLASSNAME;
        if (null !== $thumbnailBaseDir = $this->getContentThumbnailBaseDir()) {
            $thumbnailFilepath = $thumbnailBaseDir . '/' . $content['type'] . '.svg';
            if (file_exists($thumbnailFilepath)) {
                $thumbnailUrl = $this->routing->getUri(str_replace(
                    ['//', $helperClass::rootDir() . '/web'],
                    ['/', ''],
                    $thumbnailFilepath
                ));
            }
        }

        $content['thumbnail'] = $thumbnailUrl;

        return $content;
    }

    /**
     * Gets content thumbnail base directory.
     *
     * @return string
     */
    private function getContentThumbnailBaseDir()
    {
        $helperClass = self::STANDALONE_HELPER_CLASSNAME;

        return realpath($helperClass::rootDir() . '/web/static/img/contents/');
    }
}
