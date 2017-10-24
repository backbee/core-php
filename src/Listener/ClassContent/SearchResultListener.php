<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBeeCloud\Elasticsearch\SearchEvent;
use BackBee\BBApplication;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\SearchResult;
use BackBee\ClassContent\Media\Image;
use BackBee\ClassContent\Revision;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Security\Token\BBUserToken;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchResultListener
{
    const SEARCHRESULT_PRE_SEARCH_EVENT = 'content.searchresult.presearch';
    const RESULT_PER_PAGE = 10;

    public static function onRender(RendererEvent $event)
    {
        $app = $event->getApplication();
        $query = $app->getRequest()->query->get('q');
        $page = (int) $app->getRequest()->query->get('page', 1);

        $contents = [];
        $pages = [];
        $esQuery = [];
        if (is_string($query) && 0 < strlen(trim($query))) {
            $esQuery = [
                'query' => [
                    'bool' => [
                        'should' => [
                            [ 'match' => ['title' => ['query' => $query, 'boost' => 2] ] ],
                            [ 'match' => ['title.raw' => ['query' => $query, 'boost' => 2] ] ],
                            [ 'match' => ['title.folded' => ['query' => $query, 'boost' => 2] ] ],
                            [ 'match' => ['tags' => $query] ],
                            [ 'match' => ['tags.raw' => $query] ],
                            [ 'match' => ['tags.folded' => $query] ],
                            [ 'match' => ['contents' => $query] ],
                            [ 'match' => ['contents.folded' => $query] ],
                            [ 'match_phrase_prefix' => ['title' => ['query' => $query, 'boost' => 2] ] ],
                            [ 'match_phrase_prefix' => ['title.raw' => ['query' => $query, 'boost' => 2] ] ],
                            [ 'match_phrase_prefix' => ['title.folded' => ['query' => $query, 'boost' => 2] ] ],
                            [ 'match_phrase_prefix' => ['tags' => $query] ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
            ];
        }

        $searchEvent = new SearchEvent($esQuery);
        $app->getEventDispatcher()->dispatch(self::SEARCHRESULT_PRE_SEARCH_EVENT, $searchEvent);
        $esQuery = $searchEvent->getQueryBody()->getArrayCopy();
        if (false != $esQuery) {
            if (!isset($esQuery['query']['bool'])) {
                $esQuery['query']['bool'] = [];
            }

            if (!isset($esQuery['query']['bool']['must'])) {
                $esQuery['query']['bool']['must'] = [];
            }

            $esQuery['query']['bool']['must'][] = [ 'match' => ['is_pullable' => true] ];
            if (null === $app->getBBUserToken()) {
                $esQuery['query']['bool']['must'][] = [ 'match' => ['is_online' => true] ];
            }

            if (null !== $currentLang = $app->getContainer()->get('multilang_manager')->getCurrentLang()) {
                $esQuery['query']['bool']['must'][]['prefix'] = [
                    'url' => sprintf('/%s/', $currentLang),
                ];
            }

            $pages = $app->getContainer()->get('elasticsearch.manager')->customSearchPage(
                $esQuery,
                $start = ($page > 0 ? $page - 1 : 0) * self::RESULT_PER_PAGE,
                self::RESULT_PER_PAGE,
                [],
                false
            );

            foreach ($pages->collection() as $page) {
                $contents[] = self::renderPageFromRawData($event->getTarget(), $page, $app);
            }
        }

        $event->getRenderer()->assign('query', $query);
        $event->getRenderer()->assign('pages', $pages);
        $event->getRenderer()->assign('contents', $contents);
    }

    protected static function renderPageFromRawData(SearchResult $block, array $pageRawData, BBApplication $app)
    {
        $abstract = null;
        $entyMgr = $app->getEntityManager();
        $bbtoken = $app->getBBUserToken();
        if (false != $abstractUid = $pageRawData['_source']['abstract_uid']) {
            $abstract = self::getContentWithDraft(ArticleAbstract::class, $abstractUid, $entyMgr, $bbtoken);
            $abstract = $abstract ?: self::getContentWithDraft(Paragraph::class, $abstractUid, $entyMgr, $bbtoken);
            if (null !== $abstract) {
                $abstract = trim(preg_replace('#\s\s+#', ' ', preg_replace('#<[^>]+>#', ' ', $abstract->value)));
            }
        }

        $imageData = [];
        if (false != $imageUid = $pageRawData['_source']['image_uid']) {
            $image = self::getContentWithDraft(Image::class, $imageUid, $entyMgr, $bbtoken);
            if (null !== $image) {
                $imageData = [
                    'uid'    => $image->getUid(),
                    'url'    => $image->path,
                    'title'  => $image->getParamValue('title'),
                    'legend' => $image->getParamValue('description'),
                    'stat'   => $image->getParamValue('stat'),
                ];
            }
        }

        return $app->getRenderer()->partial('SearchResult/page_item.html.twig', [
            'title'                => $pageRawData['_source']['title'],
            'abstract'             => (string) $abstract,
            'url'                  => $pageRawData['_source']['url'],
            'is_online'            => $pageRawData['_source']['is_online'],
            'image'                => $imageData,
            'publishing'           => $pageRawData['_source']['published_at']
                ? new \DateTime($pageRawData['_source']['published_at'])
                : null
            ,
            'show_image'           => $block->getParamValue('show_image'),
            'show_abstract'        => $block->getParamValue('show_abstract'),
            'show_published_at'    => $block->getParamValue('show_published_at'),
        ]);
    }

    protected static function getContentWithDraft($classname, $uid, EntityManager $entyMgr, BBUserToken $bbtoken = null)
    {
        $content = $entyMgr->find($classname, $uid);
        if (null !== $content && null !== $bbtoken) {
            $draft = $entyMgr->getRepository(Revision::class)->getDraft($content, $bbtoken, false);
            $content->setDraft($draft);
        }

        return $content;
    }
}
