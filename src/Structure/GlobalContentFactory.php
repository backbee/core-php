<?php

namespace BackBeeCloud\Structure;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Footer;
use BackBee\ClassContent\Basic\Header;
use BackBee\ClassContent\Basic\Menu;
use BackBee\ClassContent\Media\Image;
use BackBee\ClassContent\Revision;
use BackBee\Site\Site;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class GlobalContentFactory
{
    use ClassContentHelperTrait;

    /**
     * @var \BackBee\Security\Token\BBUserToken
     */
    protected $bbtoken;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entyMgr;

    /**
     * @var \BackBeeCloud\Entity\ContentManager
     */
    protected $contentMgr;

    public function __construct(BBApplication $app)
    {
        $this->bbtoken = $app->getBBUserToken();
        $this->entyMgr = $app->getEntityManager();
        $this->contentMgr = $app->getContainer()->get('cloud.content_manager');
    }

    /**
     * Returns an unique instance of header menu.
     *
     * @return Menu
     */
    public function getHeaderMenu()
    {
        return $this->genericContentGetter('header_menu', Menu::class);
    }

    /**
     * Returns an unique instance of footer menu.
     *
     * @return Menu
     */
    public function getFooterMenu()
    {
        return $this->genericContentGetter('footer_menu', Menu::class);
    }

    /**
     * Returns an unique instance of header logo (classcontent: BackBee\ClassContent\Media\Image).
     *
     * @return Image
     */
    public function getHeaderLogo()
    {
        return $this->genericContentGetter('header_logo', Image::class);
    }

    /**
     * Returns an unique instance of footer logo (classcontent: BackBee\ClassContent\Media\Image).
     *
     * @return Image
     */
    public function getFooterLogo()
    {
        return $this->genericContentGetter('footer_logo', Image::class);
    }

    /**
     * Returns an unique instance of header (classcontent: BackBee\ClassContent\Basic\Header).
     *
     * @return Header
     */
    public function getHeader()
    {
        return $this->genericContentGetter('header', Header::class);
    }

    /**
     * Returns an unique instance of header (classcontent: BackBee\ClassContent\Basic\Footer).
     *
     * @return Footer
     */
    public function getFooter()
    {
        return $this->genericContentGetter('footer', Footer::class);
    }

    /**
     * Returns an unique instance of header content identified by the given name.
     *
     * @param  string $name
     * @param  string $type
     * @return AbstractClassContent
     */
    public function getHeaderContent($name, $type)
    {
        return $this->genericContentGetter('header_' . $name, $this->getClassnameFromType($type));
    }

    /**
     * Returns an unique instance of footer content identified by the given name.
     *
     * @param  string $name
     * @param  string $type
     * @return AbstractClassContent
     */
    public function getFooterContent($name, $type)
    {
        return $this->genericContentGetter('footer_' . $name, $this->getClassnameFromType($type));
    }

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
     * @param  string $type
     * @return string
     */
    protected function computeUid($type)
    {
        return md5($this->getSite()->getLabel() . '_' . $type);
    }

    /**
     * Returns the current site.
     *
     * @return Site
     */
    protected function getSite()
    {
        return $this->entyMgr->getRepository(Site::class)->findOneBy([]);
    }
}
