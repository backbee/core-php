<?php

namespace BackBeeCloud\MultiLang;

use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\Entity\PageAssociation;
use BackBeePlanet\GlobalSettings;
use BackBee\BBApplication;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;

/**
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageAssociationManager
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entyMgr;

    /**
     * @var array
     */
    protected $availables;

    /**
     * @var MultiLangManager
     */
    protected $multilangMgr;

    public function __construct(BBApplication $app)
    {
        $this->app          = $app;
        $this->entyMgr      = $app->getEntityManager();
        $this->availables   = (new GlobalSettings())->langs();
        $this->multilangMgr = $app->getContainer()->get('multilang_manager');
    }

    /**
     * Sets associated pages
     * @param Page $page - default language page
     * @param Page $target - target page
     */
    public function setAssociatedPage(Page $page, Page $target)
    {
        $pageLangDefault = $this->multilangMgr->getAssociation($page);
        $pageLangTarget  = $this->multilangMgr->getAssociation($target);

        $pageAssociationDefault = $this->entyMgr->getRepository(PageAssociation::class)->findOneBy([
            'page' => $page]);
        $pageAssociationTarget  = $this->entyMgr->getRepository(PageAssociation::class)->findOneBy([
            'page' => $target]);

        $associatedPages = $this->getAssociatedPages($page);

        if (null != $pageAssociationTarget) {
            throw new \InvalidArgumentException(
            "`{$page->getTitle()}` is already associated to a page"
            );
        }

        if (isset($associatedPages[$pageLangTarget->getLang()->getLang()])) {
            throw new \InvalidArgumentException(
            "This page already has an association for language `{$pageLangTarget->getLang()->getLang()}`"
            );
        }
        if (null === $pageAssociationDefault) {
            $pageAssociationDefault = new PageAssociation($pageLangDefault,
                $pageLangDefault->getPage());
            $this->entyMgr->persist($pageAssociationDefault);
            $this->entyMgr->flush();
        }

        $pageAssociationTarget = new PageAssociation($pageAssociationDefault->getId(),
            $pageLangTarget->getPage());
        $this->entyMgr->persist($pageAssociationTarget);
        $this->entyMgr->flush();
    }

    /**
     * Returns an array indexed by language code with the associated pages of $page
     * @param Page $page
     * @return array
     */
    public function getAssociatedPages(Page $page)
    {
        $associatedPages = [];

        $pageAssociation = $this->entyMgr->getRepository(PageAssociation::class)->findOneBy([
            'page' => $page]);
        if (null != $pageAssociation) {
            $pageAssociationArray = $this->entyMgr->getRepository(PageAssociation::class)->findBy([
                'id' => $pageAssociation->getId()]);
            foreach ($pageAssociationArray as $pageAssociation) {
                if ($this->multilangMgr->getLangByPage($pageAssociation->getPage())
                    != $this->multilangMgr->getLangByPage($page)) {
                    $associatedPages[$this->multilangMgr->getLangByPage($pageAssociation->getPage())]
                        = $pageAssociation->getPage();
                }
            }
        }

        return $associatedPages;
    }

    /**
     *
     * @param Page $page
     * @param string $lg
     * @return Page|null
     */
    public function getAssociatedPage(Page $page, $lg)
    {
        $associatedPages = $this->getAssociatedPages($page);

        return isset($associatedPages[$lg]) ? $associatedPages[$lg] : null;
    }

    /**
     * Deletes page association
     * @param Page $page
     */
    public function deleteAssociatedPage(Page $page)
    {
        $pageAssociation = $this->entyMgr->getRepository(PageAssociation::class)->findOneBy([
            'page' => $page]);

        if (null != $pageAssociation) {
            $this->entyMgr->remove($pageAssociation);
            $this->entyMgr->flush($pageAssociation);
        }
    }
}