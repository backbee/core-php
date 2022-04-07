<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\BBApplication;
use BackBee\ClassContent\Comment\Disqus;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Exception\BBException;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Site\Site;
use BackBeeCloud\MultiLang\MultiLangManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class DisqusListener
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var EntityManagerInterface
     */
    private $entityMgr;

    /**
     * @var MultiLangManager
     */
    private static $multiLangManager;

    /**
     * DisqusListener constructor.
     *
     * @param BBApplication $app
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(BBApplication $app, EntityManagerInterface $entityManager)
    {
        $this->app = $app;
        $this->entityMgr = $entityManager;
        self::$multiLangManager = $app->getContainer()->get('multilang_manager');
    }

    /**
     * Handles Disqus content to be unique per site.
     *
     * @param PreRequestEvent $event
     *
     * @throws DisqusControlledException
     */
    public function onCreateContent(PreRequestEvent $event): void
    {
        if (null === $this->app->getBBUserToken()) {
            return;
        }

        $type = $event->getRequest()->attributes->get('type');

        if ('Comment/Disqus' !== $type) {
            return;
        }

        $uid = $this->getDisqusUid();
        if (null === $disqus = $this->entityMgr->find(Disqus::class, $uid)) {
            $disqus = new Disqus($uid);
            $this->entityMgr->persist($disqus);
            $draft = $this->entityMgr->getRepository(Revision::class)->checkout($disqus, $this->app->getBBUserToken());
            $disqus->setDraft($draft);
        }

        $this->entityMgr->flush();

        throw new DisqusControlledException('');
    }

    /**
     * Handles Disqus content to be unique per site.
     *
     * @param PreRequestEvent $event
     *
     * @throws DisqusControlledException
     */
    public function onDeleteContent(PreRequestEvent $event): void
    {
        if (null === $this->app->getBBUserToken()) {
            return;
        }

        $type = $event->getRequest()->attributes->get('type');
        if ('Comment/Disqus' !== $type) {
            return;
        }

        throw new DisqusControlledException('');
    }

    /**
     * Handles DisqusControlledException.
     *
     * @param GetResponseForExceptionEvent $event
     *
     * @throws BBException
     */
    public function onDisqusControlledException(GetResponseForExceptionEvent $event): void
    {
        if (!($event->getException() instanceof DisqusControlledException)) {
            return;
        }

        $response = null;
        if ('deleteAction' === $this->app->getRequest()->attributes->get('_action')) {
            $response = new Response('', Response::HTTP_NO_CONTENT);
        } else {
            $response = new JsonResponse(
                null, Response::HTTP_CREATED, [
                    'BB-RESOURCE-UID' => $this->getDisqusUid(),
                    'Location' => $this->app->getRouting()->getUrlByRouteName(
                        'bb.rest.classcontent.get',
                        [
                            'version' => $this->app->getRequest()->attributes->get('version'),
                            'type' => 'Comment/Disqus',
                            'uid' => $this->getDisqusUid(),
                        ],
                        '',
                        false
                    ),
                ]
            );
        }

        $event->setResponse($response);
    }

    /**
     * Handle on render content.
     *
     * @param RendererEvent $event
     */
    public static function onRender(RendererEvent $event): void
    {
        $currentLang = self::$multiLangManager->isActive() ? self::$multiLangManager->getCurrentLang() : null;
        $event->getRenderer()->assign(
            'disqusId',
            $event->getTarget()->getParam('short_name_' . $currentLang) ?? $event->getTarget()->getParam('short_name')
        );
    }

    /**
     * Get Disqus uid.
     *
     * @return string
     */
    public function getDisqusUid(): string
    {
        return md5('disqus_' . $this->entityMgr->getRepository(Site::class)->findOneBy([])->getLabel());
    }
}
