<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\BBApplication;
use BackBee\ClassContent\Comment\Disqus;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PreRequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class DisqusListener
{
    protected $app;

    public function __construct(BBApplication $app)
    {
        $this->app = $app;
    }

    /**
     * Handles Disqus content to be unique per site.
     *
     * @param  PreRequestEvent $event
     */
    public function onCreateContent(PreRequestEvent $event)
    {
        if (null === $this->app->getBBUserToken()) {
            return;
        }

        $type = $event->getRequest()->attributes->get('type');
        if ('Comment/Disqus' !== $type) {
            return;
        }

        $entyMgr = $this->app->getEntityManager();
        $uid = $this->getDisqusUid();
        if (null === $disqus = $entyMgr->find(Disqus::class, $uid)) {
            $disqus = new Disqus($uid);
            $entyMgr->persist($disqus);
            $draft = $entyMgr->getRepository(Revision::class)->checkout($disqus, $this->app->getBBUserToken());
            $disqus->setDraft($draft);
        }

        $entyMgr->flush();

        throw new DisqusControlledException();
    }

    /**
     * Handles Disqus content to be unique per site.
     *
     * @param  PreRequestEvent $event
     */
    public function onDeleteContent(PreRequestEvent $event)
    {
        if (null === $this->app->getBBUserToken()) {
            return;
        }

        $type = $event->getRequest()->attributes->get('type');
        if ('Comment/Disqus' !== $type) {
            return;
        }

        throw new DisqusControlledException();
    }

    /**
     * Handles DisqusControlledException.
     *
     * @param  GetResponseForExceptionEvent $event
     */
    public function onDisqusControlledException(GetResponseForExceptionEvent $event)
    {
        if (!($event->getException() instanceof DisqusControlledException)) {
            return;
        }

        $response = null;
        if ('deleteAction' === $this->app->getRequest()->attributes->get('_action')) {
            $response = new Response('', Response::HTTP_NO_CONTENT);
        } else {
            $response = new JsonResponse(null, Response::HTTP_CREATED, [
                'BB-RESOURCE-UID' => $this->getDisqusUid(),
                'Location'        => $this->app->getRouting()->getUrlByRouteName(
                    'bb.rest.classcontent.get',
                    [
                        'version' => $this->app->getRequest()->attributes->get('version'),
                        'type'    => 'Comment/Disqus',
                        'uid'     => $this->getDisqusUid(),
                    ],
                    '',
                    false
                ),
            ]);
        }

        $event->setResponse($response);
    }

    protected function getDisqusUid()
    {
        return md5('disqus_' . $this->app->getSite()->getLabel());
    }
}
