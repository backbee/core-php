<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\ImageHandlerInterface;
use BackBee\ClassContent\Media\Image;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Event\Event;

use Jenssegers\Agent\Agent;

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

    public function onRender(RendererEvent $event)
    {
        $app = $event->getApplication();
        $renderer = $event->getRenderer();

        $block = $event->getTarget();
        $imageSmall = $block->getParamValue('image_small');
        $imageMedium = $block->getParamValue('image_medium');

        $agent = new Agent();

        if ($agent->isMobile() && $imageSmall !== null && isset($imageSmall['path'])) {
            $renderer->assign('path', $imageSmall['path']);
        }

        if ($agent->isTablet() && $imageMedium !== null && isset($imageMedium['path'])) {
            $renderer->assign('path', $imageMedium['path']);
        }
    }

    /**
     * Occurs on 'media.image.onflush'. If a new image is uploaded, this listener
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

            foreach (array_filter($paths) as $path) {
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

            $revision->setParam('focus', array(
                'left' => 50,
                'top' => 50
            ));

            $entyMgr->getUnitOfWork()->recomputeSingleEntityChangeSet(
                $entyMgr->getClassMetadata(Revision::class),
                $revision
            );
        }
    }
}
