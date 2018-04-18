<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\Listener\ClassContent\ContentAutoblockElasticsearchPreQueryEvent;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Event\Event;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentAutoblockListener
{
    const AUTOBLOCK_ID_LENGTH = 7;
    const MAX_PAGE = 5;

    public static function onPostCall(Event $event)
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
                    'uid'   => $tag->getUid(),
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
     * @param  RendererEvent  $event
     */
    public static function onRender(RendererEvent $event)
    {
        $app = $event->getApplication();
        $renderer = $event->getRenderer();
        $request = $app->getRequest();
        $block = $event->getTarget();

        $esQuery = new \ArrayObject([
            'query' => [
                'bool' => [],
            ],
        ]);

        // Building must clause
        $mustClauses = [
            [ 'match' => [ 'is_pullable' => true ] ],
        ];
        if (null === $app->getBBUserToken()) {
            $mustClauses[] = [ 'match' => [ 'is_online' => true ] ];
        }

        $esQuery['query']['bool']['must'] = $mustClauses;

        // Building should clause
        $validTags = [];
        $tagRpty = $app->getEntityManager()->getRepository(Tag::class);
        foreach ($block->getParamValue('tags') as $data) {
            if (is_array($data)) {
                if ($tag = $tagRpty->find($data['uid'])) {
                    $validTags[] = $tag->getKeyWord();
                }
            } elseif (is_string($data) && $tagRpty->exists($data)) {
                $validTags[] = $data;
            }
        }

        if (false != $validTags) {
            $criteria['tags'] = implode(',', $validTags);
        }

        $shouldClauses = [];
        foreach ($validTags as $tag) {
            $shouldClauses[] = [
                'match' => [ 'tags.raw' => strtolower($tag) ],
            ];
        }

        if (false != $shouldClauses) {
            $esQuery['query']['bool']['should'] = $shouldClauses;
            $esQuery['query']['bool']['minimum_should_match'] = 1;
        }

        // Pagination data process
        $start = (int) $block->getParamValue('start');
        $limit = (int) $block->getParamValue('limit');
        $currentPaginationPage = (int) $request->get('page_' . substr($block->getUid(), 0, 5), 1);
        if ($currentPaginationPage <= 0) {
            throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
        }

        if ($block->getParamValue('pagination')) {
            $start = ($currentPaginationPage * $limit) - $limit;
        }

        if (null !== $currentLang = $app->getContainer()->get('multilang_manager')->getCurrentLang()) {
            $esQuery['query']['bool']['must'][]['prefix'] = [
                'url' => sprintf('/%s/', $currentLang),
            ];
        }

        $sortCriteria = ['modified_at:desc'];
        if ('published_at' === $block->getParamValue('order_by')) {
            $sortCriteria = [
                'is_online:asc',
                'published_at:desc',
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

        if (0 === $pages->count() && $pages->start() >= $pages->countMax() && $currentPaginationPage !== 1) {
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

        $count = 0;
        $paginationData = [];
        if ($block->getParamValue('pagination') && false != $pages) {
            $count = $pages->countMax();
            $nbPage = ceil(($count ?: 1) / $limit);
            $startPagination = 1;

            $refNum = self::MAX_PAGE % 2 === 0
                ? self::MAX_PAGE / 2
                : (self::MAX_PAGE - 1) / 2
            ;
            $midNum = self::MAX_PAGE % 2 === 0
                ? $refNum - 1
                : $refNum
            ;

            if ($nbPage > self::MAX_PAGE) {
                if ($nbPage - $currentPaginationPage > $refNum) {
                    $startPagination = $currentPaginationPage > $refNum
                        ? $currentPaginationPage - $midNum
                        : 1
                    ;
                } else {
                    $startPagination = $nbPage - self::MAX_PAGE + 1;
                }

                if ($currentPaginationPage > $refNum) {
                    $nbPage = $nbPage - $currentPaginationPage > $refNum
                        ? $currentPaginationPage + $refNum
                        : $nbPage
                    ;
                } else {
                    $nbPage = self::MAX_PAGE;
                }
            }

            $paginationData = [
                'start_pagination' => $startPagination,
                'count'            => $count,
                'nb_page'          => $nbPage,
                'current_page'     => $currentPaginationPage,
            ];
        }

        $renderer->assign('contents', $contents);
        $renderer->assign('pagination_data', $paginationData);
    }

    public static function getAutoblockId(ContentAutoblock $autoblock)
    {
        return substr($autoblock->getUid(), 0, self::AUTOBLOCK_ID_LENGTH);
    }
}
