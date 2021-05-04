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
