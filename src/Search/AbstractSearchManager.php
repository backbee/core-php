<?php

namespace BackBeeCloud\Search;

use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\PageType\TypeInterface;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\NestedNode\Page;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractSearchManager
{
    /**
     * @var ContentManager
     */
    protected $contentMgr;

    /**
     * @var PageManager
     */
    protected $pageMgr;

    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * Constructor.
     *
     * @param PageManager    $pageMgr
     * @param ContentManager $contentMgr
     * @param EntityManager  $entyMgr
     */
    public function __construct(PageManager $pageMgr, ContentManager $contentMgr, EntityManager $entyMgr)
    {
        $this->pageMgr = $pageMgr;
        $this->contentMgr = $contentMgr;
        $this->entyMgr = $entyMgr;
    }

    /**
     * Returns the result page entity.
     *
     * @param  string|null $lang
     *
     * @return Page
     */
    abstract public function getResultPage($lang = null);

    /**
     * Builds and returns a result page according to provided uid, title and page type.
     *
     * Note that the created page's state will be online and its contents with normal
     * state (= online).
     *
     * @param  string        $uid
     * @param  string        $title
     * @param  TypeInterface $type
     * @param  string        $url
     * @param  null|string   $lang
     *
     * @return Page
     */
    protected function buildResultPage($uid, $title, TypeInterface $type, $url, $lang = null)
    {
        if (null === $page = $this->pageMgr->get($uid)) {
            $this->entyMgr->beginTransaction();

            $data = [
                'uid'   => $uid,
                'title' => $title,
                'type'  => $type->uniqueName(),
            ];

            if ($lang) {
                $data['lang'] = $lang;
            }

            $page = $this->pageMgr->create($data);
            $page->setState(Page::STATE_ONLINE);
            $page->setUrl($url);

            foreach ($this->contentMgr->getUidsFromPage($page) as $contentUid) {
                $content = $this->entyMgr->find(AbstractClassContent::class, $contentUid);
                $content->setRevision(1);
                $content->setState(AbstractClassContent::STATE_NORMAL);
            }

            $this->entyMgr->flush();
            $this->entyMgr->commit();
        }

        return $page;
    }
}
