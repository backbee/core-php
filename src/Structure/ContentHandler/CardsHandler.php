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

use BackBeeCloud\Structure\ContentHandlerInterface;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Cards;
use BackBee\ClassContent\Basic\Image;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CardsHandler implements ContentHandlerInterface
{
    use \BackBeeCloud\Structure\ClassContentHelperTrait;

    /**
     * @var ImageHandler
     */
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

        if (isset($data['image'])) {
            $newImage = $this->createOnlineContent(Image::class);
            $this->imgHandler->handle($newImage, $data['image'], true);
            $content->image = $newImage;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $config = [])
    {
        $result = isset($config['current_data']) ? $config['current_data'] : [];

        if ($content->image->image->path) {
            $result['image'] = $this->imgHandler->handleReverse($content->image, $config, true);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Cards;
    }
}
