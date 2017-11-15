<?php

namespace BackBeeCloud\Search;

use BackBeeCloud\PageType\SearchResultType;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchManager extends AbstractSearchManager
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
                'Search result',
                new SearchResultType(),
                $lang ? sprintf('/%s/search', $lang) : '/search',
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
        return md5('search_result' . ($lang ? '_' . $lang : ''));
    }
}
