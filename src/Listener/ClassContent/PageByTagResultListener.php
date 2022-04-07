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

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\NestedNode\KeyWord as Tag;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Renderer\Exception\RendererException;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Search\ResultItemHtmlFormatter;

/**
 * Class PageByTagResultListener
 *
 * @package BackBeeCloud\Listener\ClassContent
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class PageByTagResultListener
{
    /**
     * @param RendererEvent $event
     *
     * @throws RendererException
     */
    public static function onRender(RendererEvent $event): void
    {
        $app = $event->getApplication();
        $entyMgr = $app->getEntityManager();
        $request = $app->getRequest();
        $content = $event->getTarget();
        $elasticsearchQuery = $app->getContainer()->get('elasticsearch.query');

        $pages = new ElasticsearchCollection([], 0);
        $contents = [];
        $tagName = $request->attributes->get('tagName', '');

        if (
            false !== $tagName
            && null !== $tag = $entyMgr->getRepository(Tag::class)->findOneBy(['_keyWord' => $tagName])
        ) {
            $pageNum = $request->query->getInt('page', 1);
            $limit = (int)$content->getParamValue('limit') ?: SearchResultListener::RESULT_PER_PAGE;
            $start = ($pageNum > 0 ? $pageNum - 1 : 0) * $limit;

            $esQuery = [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['is_pullable' => true]],
                        ],
                        'should' => [
                            ['match' => ['tags' => $tagName]],
                            ['match' => ['tags.raw' => $tagName]],
                            ['match' => ['tags.folded' => $tagName]],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
            ];

            if (null === $app->getBBUserToken()) {
                $esQuery = $elasticsearchQuery->getQueryToFilterByPageIsOnline($esQuery, true);
            }

            if (null !== $currentLang = $app->getContainer()->get('multilang_manager')->getCurrentLang()) {
                $esQuery = $elasticsearchQuery->getQueryToFilterByLang($esQuery, [$currentLang]);
            }

            $pages = $app->getContainer()->get('elasticsearch.manager')->customSearchPage(
                $esQuery,
                $start,
                $limit,
                [],
                false
            );

            $formatter = new ResultItemHtmlFormatter(
                $app->getEntityManager(),
                $app->getRenderer(),
                $app->getLogging(),
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
        $renderer->assign('tag', $tagName);
        $renderer->assign('tag_entity', $tag);
        $renderer->assign('pages', $pages);
        $renderer->assign('contents', $contents);
    }
}
