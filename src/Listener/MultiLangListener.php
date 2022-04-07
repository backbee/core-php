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

namespace BackBeeCloud\Listener;

use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Event\Event;
use BackBee\Exception\BBException;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use BackBeeCloud\Entity\Lang;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\MultiLang\PageAssociationManager;
use BackBeeCloud\MultiLang\RedirectToDefaultLangHomeException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * Class MultiLangListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangListener
{
    public const LANG_MAIN_FALLBACK = 'en';

    /**
     * @var MultiLangManager
     */
    protected $multiLangManager;

    /**
     * @var PageAssociationManager
     */
    protected $pageAssociationManager;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * MultiLangListener constructor.
     *
     * @param MultiLangManager       $multiLangManager
     * @param PageAssociationManager $pageAssociationManager
     * @param EntityManager          $entityManager
     */
    public function __construct(
        MultiLangManager $multiLangManager,
        PageAssociationManager $pageAssociationManager,
        EntityManager $entityManager
    ) {
        $this->multiLangManager = $multiLangManager;
        $this->pageAssociationManager = $pageAssociationManager;
        $this->entityManager = $entityManager;
    }

    /**
     * On home pre call.
     *
     * @param PreRequestEvent $event
     *
     * @throws RedirectToDefaultLangHomeException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function onHomePreCall(PreRequestEvent $event): void
    {
        if ($this->multiLangManager->isActive() && '/' === $event->getTarget()->getPathInfo()) {
            $request = $event->getRequest();
            foreach ($request->getLanguages() as $langId) {
                $langId = substr($langId, 0, 2);
                if (2 === strlen($langId) && $lang = $this->entityManager->find(Lang::class, $langId)) {
                    if (!$lang->isActive()) {
                        continue;
                    }

                    $queryString = http_build_query($request->query->all());

                    throw new RedirectToDefaultLangHomeException(
                        sprintf(
                            '%s%s',
                            $this->multiLangManager->getRootByLang($lang)->getUrl(),
                            $queryString ? '?' . $queryString : ''
                        )
                    );
                }
            }

            $lang = null;
            $fallback = $this->entityManager->find(Lang::class, self::LANG_MAIN_FALLBACK);
            if ($fallback) {
                $lang = $this->multiLangManager->getLang($fallback->getLang());
            }

            if (null === $lang || (false === $lang['is_active'])) {
                $lang = $this->multiLangManager->getDefaultLang();
            }

            if (null === $lang) {
                return;
            }

            $rootUrl = sprintf('/%s/', $lang['id']);
            $rootPage = $this->entityManager->getRepository(Page::class)->findOneBy(
                [
                    '_url' => $rootUrl,
                    '_state' => Page::STATE_ONLINE,
                ]
            );

            if (null === $rootPage) {
                return;
            }

            throw new RedirectToDefaultLangHomeException($rootUrl);
        }
    }

    /**
     * On multi lang exception.
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onMultiLangException(GetResponseForExceptionEvent $event): void
    {
        if (!($event->getException() instanceof RedirectToDefaultLangHomeException)) {
            return;
        }

        $event->setResponse(new RedirectResponse($event->getException()->getRedirectTo()));
    }

    /**
     * On menu pre persist.
     *
     * @param Event $event
     *
     * @throws BBException
     */
    public function onMenuPrePersist(Event $event): void
    {
        if ($this->multiLangManager->isActive()) {
            $event->stopPropagation();

            $menu = $event->getTarget();
            $param = $menu->getParam('items');

            if (false !== $param['value']) {
                return;
            }

            if (null === $currentLang = $this->multiLangManager->getCurrentLang()) {
                return;
            }

            try {
                $lang = $this->entityManager->find(Lang::class, $currentLang);
                $homepage = $this->multiLangManager->getRootByLang($lang);
            } catch (Exception $exception) {

            }

            $menu->setParam(
                'items',
                [
                    [
                        'id' => $homepage->getUid(),
                        'url' => $homepage->getUrl(),
                        'label' => $homepage->getTitle(),
                    ],
                ]
            );
        }
    }

    /**
     * On page render.
     *
     * @param RendererEvent $event
     */
    public function onPageRender(RendererEvent $event): void
    {
        if (!$this->multiLangManager->isActive()) {
            return;
        }

        $event->getRenderer()->assign(
            'multilang_equivalent_pages',
            $this->pageAssociationManager->getEquivalentPagesData(
                $event->getTarget(),
                $event->getApplication()->getBBUserToken()
            )
        );
    }

    /**
     * On menu render.
     *
     * @param RendererEvent $event
     */
    public function onMenuRender(RendererEvent $event): void
    {
        if (!$this->multiLangManager->isActive()) {
            return;
        }

        if (null === $currentPage = $event->getRenderer()->getCurrentPage()) {
            return;
        }

        $event->getRenderer()->assign(
            'multilang_equivalent_pages',
            $this->pageAssociationManager->getEquivalentPagesData(
                $currentPage,
                $event->getApplication()->getBBUserToken()
            )
        );
    }
}
