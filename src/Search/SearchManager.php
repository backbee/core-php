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
