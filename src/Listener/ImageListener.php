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

namespace BackBeeCloud\Listener;

use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Event\Event;
use BackBee\FileSystem\ImageHandlerInterface;
use BackBee\Renderer\Event\RendererEvent;
use Cocur\Slugify\Slugify;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImageListener
{
    /**
     * @var ImageHandlerInterface
     */
    protected $imgHandler;

    /**
     * ImageListener constructor.
     *
     * @param ImageHandlerInterface $imgHandler
     */
    public function __construct(ImageHandlerInterface $imgHandler)
    {
        $this->imgHandler = $imgHandler;
    }

    /**
     * Occurs on 'rest.controller.resourcecontroller.uploadaction.postcall'. This
     * listener send the image to AWS S3 bucket.
     *
     * @param PostResponseEvent $event
     */
    public function onUploadPostCall(PostResponseEvent $event): void
    {
        $response = $event->getResponse();

        $data = json_decode($response->getContent(), true);

        preg_match('/(.*)(\.[a-z]+)$/i', $data['originalname'], $matches);

        $filename = sprintf(
            '%s/%s%s',
            substr($data['filename'], 0, 3),
            (new Slugify())->addRule('@', '')->slugify($matches[1]),
            $matches[2]
        );

        $data['path'] = $this->imgHandler->upload($filename, $data['path']);
        $data['url'] = $data['path'];

        $response->setContent(json_encode($data));
    }

    /**
     * Occurs on 'element.image.onflush'. If a new image is uploaded, this listener
     * will remove the old image from AWS S3 bucket.
     *
     * @param Event $event
     */
    public function onImageFlush(Event $event): void
    {
        $entityMgr = $event->getApplication()->getEntityManager();
        $uow = $entityMgr->getUnitOfWork();

        if ($uow->isScheduledForDelete($event->getTarget())) {
            $paths = [$event->getTarget()->path];
            foreach ($entityMgr->getRepository(Revision::class)->getRevisions($event->getTarget()) as $revision) {
                $paths[] = $revision->path;
            }

            foreach (array_unique(array_filter($paths)) as $path) {
                $this->imgHandler->delete($path);
            }

            return;
        }

        $changeSet = $uow->getEntityChangeSet($event->getTarget());

        if (
            null !== ($changeSet['_data'] ?? null) &&
            null !== ($oldData = $changeSet['_data'][0] ?? null) &&
            null !== ($newData = $changeSet['_data'][1] ?? null)
        ) {
            if (
                $oldData['path'] === $newData['path'] ||
                false !== strpos($oldData['path'][0]['scalar'], 'theme-default-resources') ||
                $uow->isScheduledForInsert($event->getTarget())
            ) {
                return;
            }

            $this->imgHandler->delete($oldData['path'][0]['scalar']);
        }
    }

    /**
     * On cloud content set render.
     *
     * @param RendererEvent $event
     */
    public function onCloudContentSetRender(RendererEvent $event): void
    {
        $block = $event->getTarget();
        if (false !== strpos($block->getParamValue('bg_image'), 'theme-default-resources')) {
            $block->setParam('bg_image', $event->getRenderer()->getCdnImageUrl($block->getParamValue('bg_image')));
        }
    }
}
