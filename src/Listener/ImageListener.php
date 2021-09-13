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

use BackBee\ApplicationInterface;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Event\Event;
use BackBee\FileSystem\ImageHandlerInterface;
use BackBee\Renderer\Event\RendererEvent;
use Cocur\Slugify\Slugify;
use Exception;

/**
 * Image listener.
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ImageListener
{
    /**
     * @var ImageHandlerInterface
     */
    protected $imgHandler;

    /**
     * @var \BackBee\ApplicationInterface
     */
    protected static $app;

    /**
     * ImageListener constructor.
     *
     * @param \BackBee\ApplicationInterface $app
     * @param ImageHandlerInterface         $imgHandler
     */
    public function __construct(ApplicationInterface $app, ImageHandlerInterface $imgHandler)
    {
        self::$app = $app;
        $this->imgHandler = $imgHandler;
    }

    /**
     * Occurs on 'rest.controller.resourcecontroller.uploadaction.postcall'.
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

    /**
     * Handle on update content.
     *
     * @param \BackBee\Controller\Event\PostResponseEvent $event
     */
    public static function onPutActionPostCall(PostResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (self::$app->getBBUserToken()) {
            $entityMgr = $event->getApplication()->getEntityManager();
            $element = $entityMgr->getRepository(AbstractClassContent::class)->find($request->request->get('uid'));
            if ($element && $element->getType() === Image::class && empty($element->getParamValue('alt'))) {
                $entityMgr->beginTransaction();
                try {
                    $element->setParam('alt', trim(strip_tags($element->getParamValue('description'))));
                    $entityMgr->flush();
                    $entityMgr->commit();
                } catch (Exception $exception) {
                    self::$app->getLogging()->error(
                        sprintf(
                            '%s : %s :%s',
                            __CLASS__,
                            __FUNCTION__,
                            $exception->getMessage()
                        )
                    );
                }
            }
        }
    }
}
