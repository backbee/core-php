<?php

namespace BackBeeCloud\Listener;

use BackBee\BBApplication;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Renderer\Renderer;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ArticleTitleListener
{
    /**
     * Called on `article.articletitle.render` event.
     *
     * @param  RendererEvent  $event
     */
    public static function onRender(RendererEvent $event)
    {
        $app = $event->getApplication();
        if (null !== $app->getBBUserToken()) {
            return;
        }

        $autoblock = null;
        $context = $app->getRequest()->query->get('context', '');
        if (ContentAutoblockListener::AUTOBLOCK_ID_LENGTH === strlen($context)) {
            $entyMgr = $app->getEntityManager();
            $qb = $entyMgr->getRepository(ContentAutoblock::class)->createQueryBuilder('c');
            $autoblock = $qb
                ->where($qb->expr()->like('c._uid', ':uid_like'))
                ->setParameter('uid_like', sprintf('%s%%', $context))
                ->getQuery()
                ->getOneOrNullResult()
            ;
        }

        if (null === $autoblock) {
            self::computeSimpleSiblings($event->getRenderer());

            return;
        }

        self::computeContextualSiblings($autoblock, $event->getRenderer());
    }

    protected static function computeSimpleSiblings(Renderer $renderer)
    {
        $baseQuery = [
            'query' => [
                'bool' => [],
            ],
        ];

        // Building must clause
        $mustClauses = [
            [ 'match' => [ 'type' => 'article' ] ],
        ];
        if (null === $renderer->getApplication()->getBBUserToken()) {
            $mustClauses[] = [ 'match' => [ 'is_online' => true ] ];
        }

        $baseQuery['query']['bool']['must'] = $mustClauses;
        $app = $renderer->getApplication();
        if (null !== $currentLang = $app->getContainer()->get('multilang_manager')->getCurrentLang()) {
            $baseQuery['query']['bool']['must'][]['prefix'] = [
                'url' => sprintf('/%s/', $currentLang),
            ];
        }

        $esMgr = $renderer->getApplication()->getContainer()->get('elasticsearch.manager');
        $prevQuery = $nextQuery = $baseQuery;

        // get previous article
        $prev = null;
        $prevQuery['query']['bool']['must'][] = [
            'range' => [
                'modified_at' => [
                    'lt' => $renderer->getCurrentPage()->getModified()->format('Y-m-d H:i:s'),
                ],
            ],
        ];
        $result = $esMgr->customSearchPage($prevQuery, null, 1, ['modified_at:desc'], false);
        if (0 < $result->count()) {
            $prev = array_pop($result->collection())['_source'];
        }

        // get next article
        $next = null;
        $nextQuery['query']['bool']['must'][] = [
            'range' => [
                'modified_at' => [
                    'gt' => $renderer->getCurrentPage()->getModified()->format('Y-m-d H:i:s'),
                ],
            ],
        ];
        $result = $esMgr->customSearchPage($nextQuery, null, 1, ['modified_at:asc'], false);
        if (0 < $result->count()) {
            $next = array_pop($result->collection())['_source'];
        }

        $renderer->assign('prev', $prev);
        $renderer->assign('next', $next);
    }

    protected static function computeContextualSiblings(ContentAutoblock $autoblock, Renderer $renderer)
    {
        $esMgr = $renderer->getApplication()->getContainer()->get('elasticsearch.manager');
        $prevQuery = $nextQuery = self::getBaseElasticsearchQuery($autoblock, $renderer->getApplication());

        // get previous article
        $prev = null;
        $prevQuery['query']['bool']['must'][] = [
            'range' => [
                'modified_at' => [
                    'lt' => $renderer->getCurrentPage()->getModified()->format('Y-m-d H:i:s'),
                ],
            ]
        ];
        $result = $esMgr->customSearchPage($prevQuery, null, 1, ['modified_at:desc'], false);
        if (0 < $result->count()) {
            $prev = array_pop($result->collection())['_source'];
            $prev['url'] = sprintf('%s?context=%s', $prev['url'], ContentAutoblockListener::getAutoblockId($autoblock));
        }

        // get next article
        $next = null;
        $nextQuery['query']['bool']['must'][] = [
            'range' => [
                'modified_at' => [
                    'gt' => $renderer->getCurrentPage()->getModified()->format('Y-m-d H:i:s'),
                ],
            ]
        ];
        $result = $esMgr->customSearchPage($nextQuery, null, 1, ['modified_at:asc'], false);
        if (0 < $result->count()) {
            $next = array_pop($result->collection())['_source'];
            $next['url'] = sprintf('%s?context=%s', $next['url'], ContentAutoblockListener::getAutoblockId($autoblock));
        }

        $renderer->assign('prev', $prev);
        $renderer->assign('next', $next);
    }

    protected static function getBaseElasticsearchQuery(ContentAutoblock $autoblock, BBApplication $app)
    {
        $esQuery = [
            'query' => [
                'bool' => [],
            ],
        ];

        // Building must clause
        $mustClauses = [
            [ 'match' => [ 'type' => 'article' ] ],
        ];

        if (null === $app->getBBUserToken()) {
            $mustClauses[] = [ 'match' => [ 'is_online' => true ] ];
        }

        $esQuery['query']['bool']['must'] = $mustClauses;

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
            $esQuery['query']['bool']['should'] = $shouldClauses;
            $esQuery['query']['bool']['minimum_should_match'] = 1;
        }

        if (null !== $currentLang = $app->getContainer()->get('multilang_manager')->getCurrentLang()) {
            $esQuery['query']['bool']['must'][]['prefix'] = [
                'url' => sprintf('/%s/', $currentLang),
            ];
        }

        return $esQuery;
    }
}
