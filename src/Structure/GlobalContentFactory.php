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

namespace BackBeeCloud\Structure;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Footer;
use BackBee\ClassContent\Basic\Header;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Basic\Menu;
use BackBee\ClassContent\Revision;
use BackBee\Security\Token\BBUserToken;
use BackBee\Site\Site;
use BackBeeCloud\Entity\ContentManager;
use BackBeePlanet\Bundle\MultiLang\MultiLangManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class GlobalContentFactory
{
    use ClassContentHelperTrait;

    /**
     * @var BBUserToken
     */
    protected $bbtoken;

    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var ContentManager
     */
    protected $contentMgr;

    /**
     * @var MultiLangManager
     */
    protected $multilangMgr;

    /**
     * GlobalContentFactory constructor.
     *
     * @param BBApplication $app
     */
    public function __construct(BBApplication $app)
    {
        $this->bbtoken = $app->getBBUserToken();
        $this->entyMgr = $app->getEntityManager();
        $this->contentMgr = $app->getContainer()->get('cloud.content_manager');
        $this->multilangMgr = $app->getContainer()->get('multilang_manager');
    }

    /**
     * Returns an unique instance of header menu.
     *
     * @return Menu
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getHeaderMenu(): Menu
    {
        return $this->genericContentGetter('header_menu', Menu::class);
    }

    /**
     * Returns an unique instance of footer menu.
     *
     * @return Menu
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getFooterMenu(): Menu
    {
        return $this->genericContentGetter('footer_menu', Menu::class);
    }

    /**
     * Returns an unique instance of header logo (classcontent: BackBee\ClassContent\Basic\Image).
     *
     * @return Image
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getHeaderLogo(): Image
    {
        return $this->genericContentGetter('header_logo', Image::class);
    }

    /**
     * Returns an unique instance of footer logo (classcontent: BackBee\ClassContent\Basic\Image).
     *
     * @return Image
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getFooterLogo(): Image
    {
        return $this->genericContentGetter('footer_logo', Image::class);
    }

    /**
     * Returns an unique instance of header logo (classcontent: BackBee\ClassContent\Basic\Image).
     *
     * @param string $name
     * 
     * @return Image
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getHeaderLogos(string $name): Image
    {
        return $this->genericContentGetter('header_logos_' . $name, Image::class);
    }

    /**
     * Returns an unique instance of footer logo (classcontent: BackBee\ClassContent\Basic\Image).
     *
     * @param string $name
     * 
     * @return Image
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getFooterLogos(string $name): Image
    {
        return $this->genericContentGetter('footer_logos_' . $name, Image::class);
    }

    /**
     * Returns an unique instance of header (classcontent: BackBee\ClassContent\Basic\Header).
     *
     * @return Header
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getHeader(): Header
    {
        return $this->genericContentGetter('header', Header::class);
    }

    /**
     * Returns an unique instance of header (classcontent: BackBee\ClassContent\Basic\Footer).
     *
     * @return Footer
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getFooter(): Footer
    {
        return $this->genericContentGetter('footer', Footer::class);
    }

    /**
     * Returns an unique instance of header content identified by the given name.
     *
     * @param string $name
     * @param string $type
     *
     * @return AbstractClassContent
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getHeaderContent(string $name, string $type): AbstractClassContent
    {
        return $this->genericContentGetter('header_' . $name, $this->getClassnameFromType($type));
    }

    /**
     * Returns an unique instance of footer content identified by the given name.
     *
     * @param string $name
     * @param string $type
     *
     * @return AbstractClassContent
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getFooterContent(string $name, string $type): AbstractClassContent
    {
        return $this->genericContentGetter('footer_' . $name, $this->getClassnameFromType($type));
    }

    /**
     * @param $lang
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function duplicateLogoForLang($lang): void
    {
        $copy = $this->contentMgr->duplicateContent(
            $this->getHeaderLogo(),
            null,
            null,
            true
        );
        $this->contentMgr->addGlobalContent($copy);

        $copy = $this->contentMgr->duplicateContent(
            $this->getFooterLogo(),
            null,
            $this->computeUid('footer_logo', $lang),
            true
        );
        $this->contentMgr->addGlobalContent($copy);
    }

    /**
     * @param $lang
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function duplicateMenuForLang($lang): void
    {
        $copy = $this->contentMgr->duplicateContent(
            $this->getHeaderMenu(),
            null,
            null,
            true
        );
        $this->contentMgr->addGlobalContent($copy);

        $copy = $this->contentMgr->duplicateContent(
            $this->getFooterMenu(),
            null,
            $this->computeUid('footer_menu', $lang),
            true
        );
        $this->contentMgr->addGlobalContent($copy);
    }

    /**
     * @param $type
     * @param $classname
     *
     * @return AbstractClassContent|object|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected function genericContentGetter($type, $classname)
    {
        if (null === $content = $this->entyMgr->find($classname, $uid = $this->computeUid($type))) {
            $content = $this->createOnlineContent($classname, $uid);
            $this->entyMgr->persist($content);
            $this->entyMgr->flush($content);
            $this->contentMgr->addGlobalContent($content);
        }

        if (null !== $this->bbtoken) {
            $draft = $this->entyMgr->getRepository(Revision::class)->getDraft($content, $this->bbtoken);
            $content->setDraft($draft);
        }

        return $content;
    }

    /**
     * Computes an unique identifier with the provided type.
     *
     * @param string $type
     * @param null   $lang
     *
     * @return string
     */
    protected function computeUid(string $type, $lang = null): string
    {
        $lang = $lang ?: $this->multilangMgr->getCurrentLang();

        return md5($this->getSite()->getLabel() . '_' . $type . ($lang ? '_' . $lang : ''));
    }

    /**
     * Returns the current site.
     *
     * @return Site
     */
    protected function getSite(): Site
    {
        return $this->entyMgr->getRepository(Site::class)->findOneBy([]);
    }
}
