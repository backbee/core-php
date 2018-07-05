<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Entity\ContentDuplicatePreSaveEvent;
use BackBeeCloud\ImageHandlerInterface;
use BackBeeCloud\UserAgentHelper;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Event\Event;
use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImageListener
{
    protected $imgHandler;

    public function __construct(ImageHandlerInterface $imgHandler)
    {
        $this->imgHandler = $imgHandler;
    }

    /**
     * Occurs on 'rest.controller.resourcecontroller.uploadaction.postcall'. This
     * listener send the image to AWS S3 bucket.
     *
     * @param  PostResponseEvent $event
     */
    public function onUploadPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();

        $data = json_decode($response->getContent(), true);
        $data['path'] = $this->imgHandler->upload($data['filename'], $data['path']);
        $data['url'] = $event->getApplication()->getRenderer()->getCdnImageUrl($data['path']);

        $response->setContent(json_encode($data));
    }

    /**
     * Occurs on 'element.image.onflush'. If a new image is uploaded, this listener
     * will remove the old image from AWS S3 bucket.
     *
     * @param  Event  $event
     */
    public function onImageFlush(Event $event)
    {
        $entyMgr = $event->getApplication()->getEntityManager();
        $uow = $entyMgr->getUnitOfWork();
        $entity = $event->getTarget();
        if ($uow->isScheduledForInsert($entity)) {
            return;
        }

        if ($uow->isScheduledForDelete($entity)) {
            $paths = [$entity->path];
            foreach ($entyMgr->getRepository(Revision::class)->getRevisions($entity) as $revision) {
                $paths[] = $revision->path;
            }

            foreach (array_unique(array_filter($paths)) as $path) {
                $this->imgHandler->delete($path);
            }

            return;
        }

        $changeset = $uow->getEntityChangeSet($entity);
        if (!isset($changeset['_data'])) {
            return;
        }

        $oldData = $changeset['_data'][0];
        $newData = $changeset['_data'][1];

        if ($oldData['path'] === $newData['path']) {
            return;
        }

        if (false !== strpos($oldData['path'][0]['scalar'], 'theme-default-resources')) {
            return;
        }

        $this->imgHandler->delete($oldData['path'][0]['scalar']);
    }

    /**
     * @todo to remove if it's not necessary anymore, not used at moment.
     */
    public function onImageRevisionFlush(Event $event)
    {
        $revision = $event->getTarget();
        $entyMgr = $event->getApplication()->getEntityManager();
        if ($entyMgr->getUnitOfWork()->isScheduledForDelete($revision)) {
            return;
        }

        if (!($revision->getContent() instanceof Image)) {
            return;
        }

        $reload = false;
        $isStretch = $revision->getParamValue('stretch');
        if ($isStretch != $revision->getParamValue('stretch_state')) {
            $reload = true;
        }

        if ($reload) {
            $revision->setParam('width', '0');

            $revision->setParam('focus', [
                'left' => 50,
                'top' => 50,
            ]);

            $entyMgr->getUnitOfWork()->recomputeSingleEntityChangeSet(
                $entyMgr->getClassMetadata(Revision::class),
                $revision
            );
        }
    }

    public function onCloudContentSetRender(RendererEvent $event)
    {
        $block = $event->getTarget();
        if (false !== strpos($block->getParamValue('bg_image'), 'theme-default-resources')) {
            $block->setParam('bg_image', $event->getRenderer()->getCdnImageUrl($block->getParamValue('bg_image')));
        }
    }
}
