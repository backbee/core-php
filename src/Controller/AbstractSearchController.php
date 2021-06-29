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

namespace BackBeeCloud\Controller;

use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\Search\AbstractSearchManager;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Renderer\Renderer;
use BackBee\Routing\RouteCollection;
use BackBeeCloud\Search\SearchManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractSearchController
{
    /**
     * @var SearchManager
     */
    protected $searchMgr;

    /**
     * @var MultiLangManager
     */
    protected $multilangMgr;

    /**
     * @var RouteCollection
     */
    protected $routing;

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * Constructor.
     *
     * @param SearchManager    $searchMgr
     * @param MultiLangManager $multilangMgr
     * @param RouteCollection  $routing
     * @param Renderer         $renderer
     */
    public function __construct(
        AbstractSearchManager $searchMgr,
        MultiLangManager $multilangMgr,
        RouteCollection $routing,
        Renderer $renderer
    ) {
        $this->searchMgr = $searchMgr;
        $this->multilangMgr = $multilangMgr;
        $this->routing = $routing;
        $this->renderer = $renderer;
    }

    /**
     * Returns Response instance with search result HTML.
     *
     * @param  null|string $lang
     *
     * @return \BackBee\NestedNode\Page
     */
    public function searchAction($lang = null, Request $request)
    {
        if (null !== $lang) {
            $data = $this->multilangMgr->getLang($lang);
            if (null === $data || !$data['is_active']) {
                throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
            }
        }

        if (null === $lang && null !== $defaultLang = $this->multilangMgr->getDefaultLang()) {
            $url = $this->getRedirectionUrlForLang($defaultLang['id'], $request);
            if (0 < $request->query->count()) {
                $url = $url . '?' . http_build_query($request->query->all());
            }

            return new RedirectResponse($url);
        }

        return new Response($this->renderer->render($this->searchMgr->getResultPage($lang)));
    }

    abstract protected function getRedirectionUrlForLang($lang, Request $request);
}
