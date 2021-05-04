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

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Basic\Slider;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SliderHandler implements ContentHandlerInterface
{
    use \BackBeeCloud\Structure\ClassContentHelperTrait;

    protected $imgHandler;

    public function __construct(ImageHandler $imgHandler)
    {
        $this->imgHandler = $imgHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!$this->supports($content)) {
            return;
        }

        $images = [];
        if (isset($data['images'])) {
            foreach ($data['images'] as $rawData) {
                $newImage = $this->createOnlineContent(Image::class);
                $this->imgHandler->handle($newImage, $rawData, true);
                $images[] = $newImage;
            }
        }

        $content->images = $images;
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $config = [])
    {
        $result = isset($config['current_data']) ? $config['current_data'] : [];
        unset($config['current_data']);

        $result['images'] = [];
        foreach((array) $content->images as $image) {
            $result['images'][] = $this->imgHandler->handleReverse($image, $config, true);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Slider;
    }
}
