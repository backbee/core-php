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

namespace BackBeePlanet\Listener;

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Renderer\Event\RendererEvent;
use BackBeePlanet\OptimizeImage\OptimizeImageManager;
use BackBeePlanet\OptimizeImage\OptimizeImageUtils;

/**
 * Class OptimizeImageListener
 *
 * @package BackBeePlanet\Listener
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class OptimizeImageListener
{
    /**
     * @var OptimizeImageManager
     */
    private $optimizeImageManager;

    /**
     * OptimizeImageListener constructor.
     *
     * @param OptimizeImageManager $optimizeImageManager
     */
    public function __construct(OptimizeImageManager $optimizeImageManager)
    {
        $this->optimizeImageManager = $optimizeImageManager;
    }

    /**
     * On cloud content set render.
     *
     * @param RendererEvent $event
     */
    public function onCloudContentSetRender(RendererEvent $event): void
    {
        $cloudContentSet = $event->getTarget();
        if (false === $bgImageUrl = $cloudContentSet->getParamValue('bg_image')) {
            return;
        }

        $filePath = $this->optimizeImageManager->getMediaPath($bgImageUrl);

        // skipping transparency png and animated gif...
        if (false === $this->optimizeImageManager->isValidToOptimize($filePath)) {
            return;
        }
    }

    /**
     * On basic image render.
     *
     * @param RendererEvent $event
     */
    public function onBasicImageRender(RendererEvent $event): void
    {
        $image = $event->getTarget();
        $renderer = $event->getRenderer();
        $renderer->assign('image_full_width_path', $image->image->path);
        $image->image->path = $this->optimizeImageManager->getOptimizeImagePath(
            (string)$image->image->path,
            (bool)$renderer->getParam('in_fluid'),
            (int)$renderer->getParam('colsize')
        );
    }

    /**
     * On image upload post call.
     *
     * @param PostResponseEvent $event
     */
    public function onImageUploadPostCall(PostResponseEvent $event): void
    {
        $response = $event->getResponse();
        $imageData = json_decode($response->getContent(), true);

        $filePath = $this->optimizeImageManager->getMediaPath($imageData['path']);

        // skipping transparency png and animated gif...
        if (false === $this->optimizeImageManager->isValidToOptimize($filePath)) {
            return;
        }

        $this->optimizeImageManager->convertAllImages($filePath);

        // replace extension
        $imageData = OptimizeImageUtils::replaceUploadDataExtension($imageData, 'jpg');

        $response->setContent(json_encode($imageData));
    }
}
