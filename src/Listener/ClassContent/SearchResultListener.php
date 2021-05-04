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

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\Exception\BBException;
use BackBee\Renderer\Event\RendererEvent;
use BackBeeCloud\Elasticsearch\SearchEvent;
use BackBeeCloud\Search\ResultItemHtmlFormatter;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SearchResultListener
{
    public const SEARCHRESULT_PRE_SEARCH_EVENT = 'content.searchresult.presearch';
    public const RESULT_PER_PAGE = 10;

    /**
     * On render.
     *
     * @param RendererEvent $event
     *
     * @throws BBException
     */
    public static function onRender(RendererEvent $event): void
    {
        $app = $event->getApplication();
        $request = $app->getRequest();

        $query = $request->query->get('q', '');

        if ('' !== $query) {
            $page = $request->query->getInt('page', 1);

            $contents = [];
            $esQuery = [];
            if (0 < strlen(trim($query))) {
                $esQuery = $app->getContainer()->get('elasticsearch.query')->getDefaultBooleanQuery($query);
                $esQuery['query']['bool']['minimum_should_match'] = 1;
            }

            $searchEvent = new SearchEvent($esQuery);
            $app->getEventDispatcher()->dispatch(self::SEARCHRESULT_PRE_SEARCH_EVENT, $searchEvent);
            $esQuery = $searchEvent->getQueryBody()->getArrayCopy();

            if (!isset($esQuery['query']['bool'])) {
                $esQuery['query']['bool'] = [];
            }

            if (!isset($esQuery['query']['bool']['must'])) {
                $esQuery['query']['bool']['must'] = [];
            }

            $esQuery['query']['bool']['must'][] = ['match' => ['is_pullable' => true]];

            if (null === $app->getBBUserToken()) {
                $esQuery['query']['bool']['must'][] = ['match' => ['is_online' => true]];
            }

            if (null !== $currentLang = $app->getContainer()->get('multilang_manager')->getCurrentLang()) {
                $esQuery['query']['bool']['must'][]['prefix'] = [
                    'url' => sprintf('/%s/', $currentLang),
                ];
            }

            $size = $searchEvent->getSize() ?: self::RESULT_PER_PAGE;
            $pages = $app->getContainer()->get('elasticsearch.manager')->customSearchPage(
                $esQuery,
                ($page > 0 ? $page - 1 : 0) * $size,
                $size,
                $searchEvent->getOrderBy() ?? ['_score', 'published_at:desc'],
                false
            );
            $formatter = new ResultItemHtmlFormatter(
                $app->getEntityManager(),
                $app->getRenderer(),
                $app->getBBUserToken()
            );

            $extraParams = [
                'show_image' => $event->getTarget()->getParamValue('show_image'),
                'show_abstract' => $event->getTarget()->getParamValue('show_abstract'),
                'show_published_at' => $event->getTarget()->getParamValue('show_published_at'),
            ];

            foreach ($pages->collection() as $page) {
                $contents[] = $formatter->renderItemFromRawData($page, $extraParams);
            }
        }

        $renderer = $event->getRenderer();
        $renderer->assign('query', $query);
        $renderer->assign('pages', $pages ?? []);
        $renderer->assign('contents', $contents ?? []);
    }
}
