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
use BackBee\DependencyInjection\Container;
use BackBee\FileSystem\ImageHandlerInterface;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * Class ImageHandler
 *
 * @package BackBeeCloud\Structure\ContentHandler
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImageHandler implements ContentHandlerInterface
{
    /**
     * @var ImageHandlerInterface
     */
    protected $imgUploadHandler;

    /**
     * @var ParameterHandler
     */
    protected $paramHandler;

    /**
     * @var Container
     */
    protected $container;

    /**
     * ImageHandler constructor.
     *
     * @param ImageHandlerInterface $imgUploadHandler
     * @param ParameterHandler      $paramHandler
     * @param Container             $container
     */
    public function __construct(
        ImageHandlerInterface $imgUploadHandler,
        ParameterHandler $paramHandler,
        Container $container
    ) {
        $this->imgUploadHandler = $imgUploadHandler;
        $this->paramHandler = $paramHandler;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data, $handleParameters = false)
    {
        if (!$this->supports($content)) {
            return;
        }

        if (isset($data['path']) && false !== $data['path']) {
            if (1 === preg_match('~^https?://~', $data['path'])) {
                $content->image->path = $this->imgUploadHandler->uploadFromUrl($data['path']);
            } else {
                $content->image->path = '/static/theme-default-resources/' . $data['path'];
            }

            $content->image->originalname = basename($data['path']);
        }

        if ($handleParameters) {
            $this->paramHandler->handle($content, $data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $config = [], $handleParameters = false)
    {
        $result = $config['current_data'] ?? [];

        $settings = $this->container->getParameter('cdn');

        $filename = '';
        if ($path = ltrim($content->image->path, '/')) {
            $filename = $config['uploadCallback']($settings['image_domain'] . '/' . $path);
        }

        $result['path'] = false === $filename ? '' : $config['themeName'] . '/' . $filename;

        if ($handleParameters) {
            $result = array_merge($result, $this->paramHandler->handleReverse($content));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Image;
    }
}
