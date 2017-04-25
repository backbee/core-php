<?php

namespace BackBeeCloud\Listener;

use BackBee\Event\Event;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentAutoblockListener
{
    const AUTOBLOCK_ID_LENGTH = 7;
    const MAX_PAGE = 10;

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

        $esQuery = [
            'query' => [
                'bool' => [],
            ],
        ];

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
        $currentPaginationPage = $request->get('page_' . substr($block->getUid(), 0, 5), 1);
        if ($block->getParamValue('pagination')) {
            $start = ($currentPaginationPage * $limit) - $limit;
        }

        // Requesting Elasticsearch to get result
        $pages = $app->getContainer()->get('elasticsearch.manager')->customSearchPage(
            $esQuery,
            $start,
            $limit + 1,
            ['modified_at:desc'],
            false
        );

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

            if ($nbPage > self::MAX_PAGE) {
                $startPagination = $currentPaginationPage > self::MAX_PAGE / 2
                    ? $currentPaginationPage - (self::MAX_PAGE / 2) + 1
                    : 1
                ;
                $nbPage = $nbPage - $currentPaginationPage > self::MAX_PAGE / 2
                    ? $currentPaginationPage + (self::MAX_PAGE / 2)
                    : $nbPage
                ;

                if ($nbPage - $startPagination < self::MAX_PAGE - 1) {
                    $nbPage = $nbPage + self::MAX_PAGE - $nbPage;
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
