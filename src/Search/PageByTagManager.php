<?php

namespace BackBeeCloud\Search;

use BackBeeCloud\PageType\PageByTagResultType;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageByTagManager extends AbstractSearchManager
{
    /**
     * {@inheritdoc}
     */
    public function getResultPage($lang = null)
    {
        $uid = $this->getResultPageUid($lang);
        if (null === $page = $this->pageMgr->get($uid)) {
            $page = $this->buildResultPage(
                $uid,
                'Search by tag result',
                new PageByTagResultType(),
                $lang ? sprintf('/%s/pages/tag/', $lang) : '/pages/tag/',
                $lang
            );
        }

        return $page;
    }

    /**
     * Returns uid for page by tag result page.
     *
     * @param  null|string $lang
     *
     * @return string
     */
    protected function getResultPageUid($lang = null)
    {
        return md5('page_by_tag_result_page' . ($lang ? '_' . $lang : ''));
    }
}
