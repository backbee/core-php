<?php

namespace BackBee\Renderer\Helper;

use BackBee\NestedNode\Page;
use BackBee\Renderer\Helper\AbstractHelper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getPageTags extends AbstractHelper
{
    public function __invoke(Page $page)
    {
        $entyMgr = $this->_renderer->getApplication()->getEntityManager();

        $pagetag = $entyMgr->getRepository('BackBeeCloud\Entity\PageTag')->findOneBy(['page' => $page]);
        $tags = $pagetag ? $pagetag->getTags() : [];
        $result = [];
        foreach ($tags as $tag) {
            $result[] = $tag->getKeyWord();
        }

        return $result;
    }
}
