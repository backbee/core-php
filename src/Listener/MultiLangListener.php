<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\MultiLang\RedirectToDefaultLangHomeException;
use BackBeeCloud\MultiLang\WorkInProgressException;
use BackBee\Bundle\Registry;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Utils\StringUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangListener
{
    public static function onApplicationStart(Event $event)
    {
        if (!$event->getApplication()->getBBUserToken()) {
            return;
        }

        if ('/api/langs/work-progress' === $event->getApplication()->getRequest()->getPathInfo()) {
            return;
        }

        try {
            $event->getApplication()->getContainer()->get('multilang_manager')->getWorkProgress();
        } catch (\LogicException $e) {
            return;
        }

        throw new WorkInProgressException();
    }

    public static function onHomePreCall(PreRequestEvent $event)
    {
        $app = $event->getApplication();
        $multilangMgr = $app->getContainer()->get('multilang_manager');
        if ('/' !== $event->getTarget()->getPathInfo() || null === $multilangMgr->getDefaultLang()) {
            return;
        }

        $entyMgr = $app->getEntityManager();
        foreach ($app->getRequest()->getLanguages() as $langId) {
            if (2 === strlen($langId) && $lang = $entyMgr->find(Lang::class, $langId)) { // TO REWORK
                throw new RedirectToDefaultLangHomeException($multilangMgr->getRootByLang($lang)->getUrl());
            }
        }

        if (null === $lang = $multilangMgr->getDefaultLang()) {
            return;
        }

        $rootUrl = sprintf('/%s/', $lang['id']);
        $rootPage = $entyMgr->getRepository(Page::class)->findOneBy([
            '_url'   => $rootUrl,
            '_state' => Page::STATE_ONLINE,
        ]);

        if (null === $rootPage) {
            return;
        }

        throw new RedirectToDefaultLangHomeException($rootUrl);

    }

    public static function onMultiLangException(GetResponseForExceptionEvent $event)
    {
        if (!($event->getException() instanceof RedirectToDefaultLangHomeException)) {
            return;
        }

        $event->setResponse(new RedirectResponse($event->getException()->getRedirectTo()));
    }

    public static function onMenuPrePersist(Event $event)
    {
        $multilangMgr = $event->getApplication()->getContainer()->get('multilang_manager');
        if (null === $multilangMgr->getDefaultLang()) {
            return;
        }

        $event->stopPropagation();

        $menu = $event->getTarget();
        $param = $menu->getParam('items');
        if (false != $param['value']) {
            return;
        }

        if (null === $currentLang = $multilangMgr->getCurrentLang()) {
            return;
        }

        $lang = $event->getApplication()->getEntityManager()->find(Lang::class, $currentLang);
        $homepage = $multilangMgr->getRootByLang($lang);

        $menu->setParam('items', [
            [
                'id'    => $homepage->getUid(),
                'url'   => $homepage->getUrl(),
                'label' => $homepage->getTitle(),
            ],
        ]);
    }
}
