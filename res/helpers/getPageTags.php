<?php

namespace BackBee\Renderer\Helper;

use BackBeeCloud\Entity\PageTag;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Helper\AbstractHelper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getPageTags extends AbstractHelper
{
    public function __invoke(Page $page, $rawResult = false)
    {
        $entyMgr = $this->_renderer->getApplication()->getEntityManager();

        $pagetag = $entyMgr->getRepository(PageTag::class)->findOneBy([
            'page' => $page,
        ]);
        $tags = $pagetag ? $pagetag->getTags() : [];
        if ($rawResult) {
            return $tags;
        }

        $result = [];
        foreach ($tags as $tag) {
            $result[] = $tag->getKeyWord();
        }

        return $result;
    }
}
