<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\MultiLang\RedirectToDefaultLangHomeException;
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
    const LANG_MAIN_FALLBACK = 'en';

    public static function onHomePreCall(PreRequestEvent $event)
    {
        $app = $event->getApplication();
        $multilangMgr = $app->getContainer()->get('multilang_manager');
        if ('/' !== $event->getTarget()->getPathInfo() || null === $multilangMgr->getDefaultLang()) {
            return;
        }

        $entyMgr = $app->getEntityManager();
        foreach ($app->getRequest()->getLanguages() as $langId) {
            $langId = substr($langId, 0, 2);
            if (2 === strlen($langId) && $lang = $entyMgr->find(Lang::class, $langId)) {
                if (!$lang->isActive()) {
                    continue;
                }

                $queryString = http_build_query($event->getTarget()->query->all());

                throw new RedirectToDefaultLangHomeException(sprintf(
                    '%s%s',
                    $multilangMgr->getRootByLang($lang)->getUrl(),
                    $queryString ? '?' . $queryString : ''
                ));
            }
        }

        $lang = null;
        $fallback = $entyMgr->find(Lang::class, self::LANG_MAIN_FALLBACK);
        if ($fallback) {
            $lang = $multilangMgr->getLang($fallback->getLang());
        }

        if (
            null === $lang
            || (null !== $lang && false === $lang['is_active'])
        ) {
            $lang = $multilangMgr->getDefaultLang();
        }

        if (null === $lang) {
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
