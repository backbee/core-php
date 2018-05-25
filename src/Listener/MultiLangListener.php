<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\MultiLang\PageAssociationManager;
use BackBeeCloud\MultiLang\RedirectToDefaultLangHomeException;
use BackBee\Bundle\Registry;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Security\Token\BBUserToken;
use BackBee\Utils\StringUtils;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangListener
{
    const LANG_MAIN_FALLBACK = 'en';

    /**
     * @var MultiLangManager
     */
    protected $multilangManager;

    /**
     * @var PageAssociationManager
     */
    protected $pageAssociationManager;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(
        MultiLangManager $multilangManager,
        PageAssociationManager $pageAssociationManager,
        EntityManager $entityManager
    ) {
        $this->multilangManager = $multilangManager;
        $this->pageAssociationManager = $pageAssociationManager;
        $this->entityManager = $entityManager;
    }

    public function onHomePreCall(PreRequestEvent $event)
    {
        if (
            '/' !== $event->getTarget()->getPathInfo()
            || !$this->multilangManager->isActive()
        ) {
            return;
        }

        $request = $event->getRequest();
        foreach ($request->getLanguages() as $langId) {
            $langId = substr($langId, 0, 2);
            if (2 === strlen($langId) && $lang = $this->entityManager->find(Lang::class, $langId)) {
                if (!$lang->isActive()) {
                    continue;
                }

                $queryString = http_build_query($request->query->all());

                throw new RedirectToDefaultLangHomeException(sprintf(
                    '%s%s',
                    $this->multilangManager->getRootByLang($lang)->getUrl(),
                    $queryString ? '?' . $queryString : ''
                ));
            }
        }

        $lang = null;
        $fallback = $this->entityManager->find(Lang::class, self::LANG_MAIN_FALLBACK);
        if ($fallback) {
            $lang = $this->multilangManager->getLang($fallback->getLang());
        }

        if (
            null === $lang
            || (null !== $lang && false === $lang['is_active'])
        ) {
            $lang = $this->multilangManager->getDefaultLang();
        }

        if (null === $lang) {
            return;
        }

        $rootUrl = sprintf('/%s/', $lang['id']);
        $rootPage = $this->entityManager->getRepository(Page::class)->findOneBy([
            '_url'   => $rootUrl,
            '_state' => Page::STATE_ONLINE,
        ]);

        if (null === $rootPage) {
            return;
        }

        throw new RedirectToDefaultLangHomeException($rootUrl);
    }

    public function onMultiLangException(GetResponseForExceptionEvent $event)
    {
        if (!($event->getException() instanceof RedirectToDefaultLangHomeException)) {
            return;
        }

        $event->setResponse(new RedirectResponse($event->getException()->getRedirectTo()));
    }

    public function onMenuPrePersist(Event $event)
    {
        if (!$this->multilangManager->isActive()) {
            return;
        }

        $event->stopPropagation();

        $menu = $event->getTarget();
        $param = $menu->getParam('items');
        if (false != $param['value']) {
            return;
        }

        if (null === $currentLang = $this->multilangManager->getCurrentLang()) {
            return;
        }

        $lang = $this->entityManager->find(Lang::class, $currentLang);
        $homepage = $this->multilangManager->getRootByLang($lang);

        $menu->setParam('items', [
            [
                'id'    => $homepage->getUid(),
                'url'   => $homepage->getUrl(),
                'label' => $homepage->getTitle(),
            ],
        ]);
    }

    public function onPageRender(RendererEvent $event)
    {
        if (!$this->multilangManager->isActive()) {
            return;
        }

        $event->getRenderer()->assign(
            'multilang_equivalent_pages',
            $this->getEquivalentPagesData(
                $event->getTarget(),
                $event->getApplication()->getBBUserToken()
            )
        );
    }

    public function onMenuRender(RendererEvent $event)
    {
        if (!$this->multilangManager->isActive()) {
            return;
        }

        if (null === $currentPage = $event->getRenderer()->getCurrentPage()) {
            return;
        }

        $event->getRenderer()->assign(
            'multilang_equivalent_pages',
            $this->getEquivalentPagesData(
                $currentPage,
                $event->getApplication()->getBBUserToken()
            )
        );
    }

    protected function getEquivalentPagesData(Page $currentPage, BBUserToken $bbtoken = null)
    {
        $equivalentPages = [];
        foreach ($this->pageAssociationManager->getAssociatedPages($currentPage) as $lang => $page) {
            if ($page->isOnline() || null !== $bbtoken) {
                $equivalentPages[$lang] = $page;
            }
        }

        return $equivalentPages;
    }
}
