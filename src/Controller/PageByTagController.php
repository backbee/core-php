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

namespace BackBeeCloud\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Class PageByTagController
 *
 * @package BackBeeCloud\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageByTagController extends AbstractSearchController
{
    /**
     * Get redirection url for lang.
     *
     * @param         $lang
     * @param Request $request
     *
     * @return string
     */
    protected function getRedirectionUrlForLang($lang, Request $request): string
    {
        return $this->routing->getUrlByRouteName(
            'cloud.search_by_tag_i18n',
            [
                'lang' => $lang,
                'tagName' => $request->attributes->get('tagName', ''),
            ],
            null,
            false
        );
    }
}

