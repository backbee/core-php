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

namespace BackBeeCloud\Listener;

use BackBee\ClassContent\ContentAutoblock;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Event\Event;
use BackBee\Exception\BBException;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\Renderer\Event\RendererEvent;
use BackBeeCloud\Listener\ClassContent\ContentAutoblockElasticsearchPreQueryEvent;
use BackBeeCloud\Revision\RevisionManager;
use Exception;

/**
 * Class ContentAutoblockListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentAutoblockListener
{
    public const AUTOBLOCK_ID_LENGTH = 7;
    public const MAX_PAGE = 5;

    /**
     * On post call.
     *
     * @param Event $event
     */
    public static function onPostCall(Event $event): void
    {
        $app = $event->getApplication();
        $response = $event->getResponse();
        $data = json_decode($response->getContent(), true);
        if ('ContentAutoblock' !== $data['type']) {
            return;
        }

        $formattedTags = [];
        foreach ($data['parameters']['tags']['value'] as $rawTag) {
            $tag = null;
            if (is_array($rawTag)) {
                $tag = $app->getEntityManager()->getRepository(Tag::class)->find($rawTag['uid']);
            } elseif (is_string($rawTag)) {
                $tag = $app->getEntityManager()->getRepository(Tag::class)->exists($rawTag);
            }

            if (null !== $tag) {
                $formattedTags[] = [
                    'uid' => $tag->getUid(),
                    'label' => $tag->getKeyWord(),
                ];
            }
        }

        $data['parameters']['tags']['value'] = $formattedTags;
        $response->setContent(json_encode($data));
    }

    /**
     * Called on `contentautoblock.render` event.
     *
     * @param RendererEvent $event
     *
     * @throws FrontControllerException
     * @throws BBException
     * @throws Exception
     */
    public static function onRender(RendererEvent $event): void
    {
        $app = $event->getApplication();
        $renderer = $event->getRenderer();
        $request = $app->getRequest();
        $block = $event->getTarget();

        $elasticsearchQuery = $app->getContainer()->get('elasticsearch.query');
        $esQuery = $elasticsearchQuery->getBaseQuery(null, true);
        $esQuery = $app->getContainer()->get('elasticsearch.query')->getSearchQueryByTag(
            $esQuery,
            $block->getParamValue('tags'),
            true
        );

        // Pagination data process
        $start = (int)$block->getParamValue('start');
        $limit = (int)$block->getParamValue('limit');
        $currentPaginationPage = (int)$request->get('page_' . substr($block->getUid(), 0, 5), 1);
        if ($currentPaginationPage <= 0) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }

        if ($block->getParamValue('pagination')) {
            $start = ($currentPaginationPage * $limit) - $limit;
        }

        $sortCriteria = ['modified_at:desc'];
        if ('published_at' === $block->getParamValue('order_by')) {
            $sortCriteria = [
                'published_at:desc',
                'is_online:asc',
            ];
        }

        $app->getEventDispatcher()->dispatch(
            ContentAutoblockElasticsearchPreQueryEvent::EVENT_NAME,
            new ContentAutoblockElasticsearchPreQueryEvent($block, $esQuery)
        );

        // Requesting Elasticsearch to get result
        $pages = $app->getContainer()->get('elasticsearch.manager')->customSearchPage(
            $esQuery->getArrayCopy(),
            $start,
            $limit + 1,
            $sortCriteria,
            false
        );

        if ($currentPaginationPage !== 1 && 0 === $pages->count() && $pages->start() >= $pages->countMax()) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }

        $contents = [];
        $currentpage = $event->getRenderer()->getCurrentPage();
        foreach ($pages as $page) {
            if (count($contents) === $limit) {
                break;
            }

            if ($page['_id'] === $currentpage->getUid()) {
                continue;
            }

            $contents[] = HighlightContentListener::renderPageFromRawData(
                $page,
                $block,
                $renderer->getMode(),
                $app
            );
        }

        $paginationData = [];
        if (false !== $pages && $block->getParamValue('pagination')) {
            $count = $pages->countMax();
            $nbPage = ceil(($count ?: 1) / $limit);
            $startPagination = 1;
            $refNum = self::MAX_PAGE % 2 === 0 ? self::MAX_PAGE / 2 : (self::MAX_PAGE - 1) / 2;
            $midNum = self::MAX_PAGE % 2 === 0 ? $refNum - 1 : $refNum;

            if ($nbPage > self::MAX_PAGE) {
                if ($nbPage - $currentPaginationPage > $refNum) {
                    $startPagination = $currentPaginationPage > $refNum
                        ? $currentPaginationPage - $midNum
                        : 1;
                } else {
                    $startPagination = $nbPage - self::MAX_PAGE + 1;
                }

                if ($currentPaginationPage > $refNum) {
                    $nbPage = $nbPage - $currentPaginationPage > $refNum
                        ? $currentPaginationPage + $refNum
                        : $nbPage;
                } else {
                    $nbPage = self::MAX_PAGE;
                }
            }

            $paginationData = [
                'start_pagination' => $startPagination,
                'count' => $count,
                'nb_page' => $nbPage,
                'current_page' => $currentPaginationPage,
            ];
        }

        $renderer->assign('contents', $contents);
        $renderer->assign('pagination_data', $paginationData);
    }

    /**
     * Get autoblock id.
     *
     * @param ContentAutoblock $autoblock
     *
     * @return false|string
     */
    public static function getAutoblockId(ContentAutoblock $autoblock)
    {
        return substr($autoblock->getUid(), 0, self::AUTOBLOCK_ID_LENGTH);
    }
}
