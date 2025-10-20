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

namespace BackBee\Renderer\Helper;

use BackBeeCloud\Entity\PageTag;
use BackBee\NestedNode\Page;

/**
 * Class getPageTags
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@team-one.fr>
 */
class getPageTags extends AbstractHelper
{
    /**
     * Get the tags associated with this page.
     *
     * @param null|\BackBee\NestedNode\Page $page
     * @param bool                          $rawResult
     *
     * @return array
     */
    public function __invoke(?Page $page, bool $rawResult = false)
    {
        if ($page === null) {
            return [];
        }

        $entyMgr = $this->_renderer->getApplication()->getEntityManager();

        $pageTag = $entyMgr->getRepository(PageTag::class)->findOneBy([
            'page' => $page,
        ]);

        $tags = $pageTag ? $pageTag->getTags() : [];

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
