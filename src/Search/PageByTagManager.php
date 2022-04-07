<?php

/*
 * Copyright (c) 2022 Obione
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

use BackBee\NestedNode\Page;
use BackBeeCloud\PageType\PageByTagResultType;

/**
 * Class PageByTagManager
 *
 * @package BackBeeCloud\Search
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageByTagManager extends AbstractSearchManager
{
    /**
     * {@inheritdoc}
     */
    public function getResultPage($lang = null): ?Page
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
    protected function getResultPageUid($lang = null): string
    {
        return md5('page_by_tag_result_page' . ($lang ? '_' . $lang : ''));
    }
}
