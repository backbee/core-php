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
use BackBee\ClassContent\Revision;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Renderer\Renderer;
use Exception;
use Psr\Log\LoggerInterface;
use function in_array;
use function strlen;

/**
 * Class ArticleTitleListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class ArticleTitleListener
{
    public const ORDER_BY_PUBLISH_DATE = 'published_at';
    public const ORDER_BY_MODIFICATION_DATE = 'modified_at';

    /**
     * Called on `article.articletitle.render` event.
     *
     * @param RendererEvent $event
     */
    public static function onRender(RendererEvent $event): void
    {
        $app = $event->getApplication();
        $entityMgr = $app->getEntityManager();
        $renderer = $event->getRenderer();
        $autoBlock = null;
        $context = $app->getRequest()->query->get('ref', '');

        if (ContentAutoblockListener::AUTOBLOCK_ID_LENGTH === strlen($context)) {
            try {
                $qb = $entityMgr->getRepository(ContentAutoblock::class)->createQueryBuilder('c');
                $autoBlock = $qb
                    ->where($qb->expr()->like('c._uid', ':uid_like'))
                    ->setParameter('uid_like', sprintf('%s%%', $context))
                    ->getQuery()
                    ->getOneOrNullResult();

            } catch (Exception $exception) {
                $app->getLogging()->error(
                    sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
                );
            }
        }

        if (null === $currentPage = $renderer->getCurrentPage()) {
            return;
        }

        if (null === $autoBlock) {
            self::computeSimpleSiblings($renderer, $currentPage);

            return;
        }

        try {
            if (null !== $app->getBBUserToken()) {
                $draft = $entityMgr->getRepository(Revision::class)->getDraft($autoBlock, $app->getBBUserToken());
                $autoBlock->setDraft($draft);
            }
        } catch (Exception $exception) {
            $app->getLogging()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        self::computeContextualSiblings($autoBlock, $renderer, $currentPage, $app->getLogging());
    }

    /**
     * Compute simple siblings.
     *
     * @param Renderer $renderer
     * @param Page     $currentPage
     */
    protected static function computeSimpleSiblings(Renderer $renderer, Page $currentPage): void
    {
        $app = $renderer->getApplication();
        $elasticsearchQuery = $app->getContainer()->get('elasticsearch.query');
        $nextQuery = $elasticsearchQuery->getBaseQuery($currentPage);
        $prevQuery = $nextQuery;
        $esMgr = $app->getContainer()->get('elasticsearch.manager');

        // get previous article
        $prev = null;
        $prevQuery['query']['bool']['must'][] = [
            'range' => [
                'modified_at' => [
                    'lt' => $currentPage->getModified()->format('Y-m-d H:i:s'),
                ],
            ],
        ];
        $result = $esMgr->customSearchPage($prevQuery, 0, 1, ['modified_at:desc'], false);
        if (0 < $result->count()) {
            $collection = $result->collection();
            $prev = array_pop($collection)['_source'];
        }

        // get next article
        $next = null;
        $nextQuery['query']['bool']['must'][] = [
            'range' => [
                'modified_at' => [
                    'gt' => $currentPage->getModified()->format('Y-m-d H:i:s'),
                ],
            ],
        ];
        $result = $esMgr->customSearchPage($nextQuery, 0, 1, ['modified_at:asc'], false);
        if (0 < $result->count()) {
            $collection = $result->collection();
            $next = array_pop($collection)['_source'];
        }

        $renderer->assign('prev', $prev);
        $renderer->assign('next', $next);
    }

    /**
     * Compute contextual siblings.
     *
     * @param ContentAutoblock $autoblock
     * @param Renderer         $renderer
     * @param Page             $currentPage
     * @param LoggerInterface  $logger
     */
    protected static function computeContextualSiblings(
        ContentAutoblock $autoblock,
        Renderer $renderer,
        Page $currentPage,
        LoggerInterface $logger
    ): void {
        $orderBy = $autoblock->getParamValue('order_by');
        if (!in_array($orderBy, [self::ORDER_BY_MODIFICATION_DATE, self::ORDER_BY_PUBLISH_DATE], true)) {
            $logger->error($orderBy . ' value for ContentAutoblock "order_by" parameter is not supported');

            return;
        }

        if (self::ORDER_BY_PUBLISH_DATE === $orderBy && !$currentPage->isOnline()) {
            return;
        }

        $app = $renderer->getApplication();
        $elasticsearchQuery = $app->getContainer()->get('elasticsearch.query');
        $baseQuery = $elasticsearchQuery->getBaseQuery($currentPage);
        $baseQuery = $elasticsearchQuery->getSearchQueryByTag($baseQuery, $autoblock->getParamValue('tags'));
        $nextQuery = $baseQuery;
        $prevQuery = $nextQuery;
        $esMgr = $app->getContainer()->get('elasticsearch.manager');
        $getDateMethod = self::ORDER_BY_PUBLISH_DATE === $orderBy ? 'getPublishing' : 'getModified';

        // get previous article
        $prev = null;
        $prevQuery['query']['bool']['filter'] = [
            'range' => [
                $orderBy => [
                    'lt' => $currentPage->$getDateMethod()->format('Y-m-d H:i:s'),
                ],
            ],
        ];

        $result = $esMgr->customSearchPage($prevQuery, 0, 1, [$orderBy . ':desc'], false);
        if (0 < $result->count()) {
            $collection = $result->collection();
            $prev = array_pop($collection)['_source'];
            $prev['url'] = sprintf('%s?ref=%s', $prev['url'], ContentAutoblockListener::getAutoblockId($autoblock));
        }

        // get next article
        $next = null;
        $nextQuery['query']['bool']['must'][] = [
            'range' => [
                $orderBy => [
                    'gt' => $currentPage->$getDateMethod()->format('Y-m-d H:i:s'),
                ],
            ],
        ];
        $result = $esMgr->customSearchPage($nextQuery, 0, 1, ['modified_at:asc'], false);
        if (0 < $result->count()) {
            $collection = $result->collection();
            $next = array_pop($collection)['_source'];
            $next['url'] = sprintf('%s?ref=%s', $next['url'], ContentAutoblockListener::getAutoblockId($autoblock));
        }

        $renderer->assign('prev', $prev);
        $renderer->assign('next', $next);
    }
}
