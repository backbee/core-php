<?php

namespace BackBeeCloud\Listener;

use BackBee\BBApplication;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\ClassContent\Revision;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Renderer\Renderer;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ArticleTitleListener
{
    const ORDER_BY_PUBLISH_DATE = 'published_at';
    const ORDER_BY_MODIFICATION_DATE = 'modified_at';

    /**
     * Called on `article.articletitle.render` event.
     *
     * @param  RendererEvent  $event
     */
    public static function onRender(RendererEvent $event)
    {
        $app = $event->getApplication();
        $entyMgr = $app->getEntityManager();

        $autoblock = null;
        $context = $app->getRequest()->query->get('context', '');
        if (ContentAutoblockListener::AUTOBLOCK_ID_LENGTH === strlen($context)) {
            $qb = $entyMgr->getRepository(ContentAutoblock::class)->createQueryBuilder('c');
            $autoblock = $qb
                ->where($qb->expr()->like('c._uid', ':uid_like'))
                ->setParameter('uid_like', sprintf('%s%%', $context))
                ->getQuery()
                ->getOneOrNullResult()
            ;
        }

        $renderer = $event->getRenderer();
        if (null === $currentPage = $renderer->getCurrentPage()) {
            return;
        }

        if (null === $autoblock) {
            self::computeSimpleSiblings($renderer, $currentPage);

            return;
        }

        if (null !== $bbtoken = $app->getBBUserToken()) {
            $draft = $entyMgr->getRepository(Revision::class)->getDraft($autoblock, $bbtoken, false);
            $autoblock->setDraft($draft);
        }

        self::computeContextualSiblings($autoblock, $renderer, $currentPage);
    }

    protected static function computeSimpleSiblings(Renderer $renderer, Page $currentPage)
    {
        $app = $renderer->getApplication();
        $prevQuery = $nextQuery = self::getBaseElasticsearchQuery($app, $currentPage);
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
        $result = $esMgr->customSearchPage($prevQuery, null, 1, ['modified_at:desc'], false);
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
        $result = $esMgr->customSearchPage($nextQuery, null, 1, ['modified_at:asc'], false);
        if (0 < $result->count()) {
            $collection = $result->collection();
            $next = array_pop($collection)['_source'];
        }

        $renderer->assign('prev', $prev);
        $renderer->assign('next', $next);
    }

    protected static function computeContextualSiblings(ContentAutoblock $autoblock, Renderer $renderer, Page $currentPage)
    {
        $orderBy = $autoblock->getParamValue('order_by');
        if (!in_array($orderBy, [self::ORDER_BY_MODIFICATION_DATE, self::ORDER_BY_PUBLISH_DATE])) {
            error_log($orderBy.' value for ContentAutoblock "order_by" parameter is not supported');

            return;
        }

        if (self::ORDER_BY_PUBLISH_DATE === $orderBy && !$currentPage->isOnline()) {
            return;
        }

        $app = $renderer->getApplication();
        $baseQuery = self::getBaseElasticsearchQuery($app, $currentPage);

        // Building should clause
        $validTags = [];
        $tagRpty = $app->getEntityManager()->getRepository(Tag::class);
        foreach ($autoblock->getParamValue('tags') as $data) {
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
            $baseQuery['query']['bool']['should'] = $shouldClauses;
            $baseQuery['query']['bool']['minimum_should_match'] = 1;
        }

        $prevQuery = $nextQuery = $baseQuery;
        $esMgr = $app->getContainer()->get('elasticsearch.manager');

        $getDateMethod = self::ORDER_BY_PUBLISH_DATE === $orderBy
            ? 'getPublishing'
            : 'getModified';

        // get previous article
        $prev = null;
        $prevQuery['query']['bool']['must'][] = [
            'range' => [
                $orderBy => [
                    'lt' => $currentPage->$getDateMethod()->format('Y-m-d H:i:s'),
                ],
            ]
        ];

        $result = $esMgr->customSearchPage($prevQuery, null, 1, ['modified_at:desc'], false);
        if (0 < $result->count()) {
            $collection = $result->collection();
            $prev = array_pop($collection)['_source'];
            $prev['url'] = sprintf('%s?context=%s', $prev['url'], ContentAutoblockListener::getAutoblockId($autoblock));
        }

        // get next article
        $next = null;
        $nextQuery['query']['bool']['must'][] = [
            'range' => [
                $orderBy => [
                    'gt' => $currentPage->$getDateMethod()->format('Y-m-d H:i:s'),
                ],
            ]
        ];
        $result = $esMgr->customSearchPage($nextQuery, null, 1, ['modified_at:asc'], false);
        if (0 < $result->count()) {
            $collection = $result->collection();
            $next = array_pop($collection)['_source'];
            $next['url'] = sprintf('%s?context=%s', $next['url'], ContentAutoblockListener::getAutoblockId($autoblock));
        }

        $renderer->assign('prev', $prev);
        $renderer->assign('next', $next);
    }

    protected static function getBaseElasticsearchQuery(BBApplication $app, Page $page)
    {
        $esQuery = [
            'query' => [
                'bool' => [],
            ],
        ];

        // Building must clause
        $typeUniqueName = '';
        if ($page) {
            $pageTypeManager = $app->getContainer()->get('cloud.page_type.manager');
            $typeUniqueName = $pageTypeManager->findByPage($page)->uniqueName();
        }

        $mustClauses = [];
        if ($typeUniqueName) {
            $mustClauses = [
                [ 'match' => [ 'type' => $typeUniqueName ] ],
            ];
        }

        if (null === $app->getBBUserToken()) {
            $mustClauses[] = [ 'match' => [ 'is_online' => true ] ];
        }

        $esQuery['query']['bool']['must'] = $mustClauses;

        if (null !== $currentLang = $app->getContainer()->get('multilang_manager')->getCurrentLang()) {
            $esQuery['query']['bool']['must'][]['prefix'] = [
                'url' => sprintf('/%s/', $currentLang),
            ];
        }

        return $esQuery;
    }
}
