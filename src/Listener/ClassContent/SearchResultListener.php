<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\BBApplication;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Media\Image;
use BackBee\ClassContent\Revision;
use BackBee\ClassContent\Basic\SearchResult;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Security\Token\BBUserToken;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchResultListener
{
    const RESULT_PER_PAGE = 10;

    public static function onRender(RendererEvent $event)
    {
        $block = $event->getTarget();
        $renderer = $event->getRenderer();
        $app = $event->getApplication();
        $query = $app->getRequest()->query->get('q');
        $page = (int) $app->getRequest()->query->get('page', 1);
        $contents = [];
        $pages = [];
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

            $mustClauses = [
                [ 'match' => ['is_pullable' => true] ],
            ];
            if (null === $app->getBBUserToken()) {
                $mustClauses[] = [ 'match' => ['is_online' => true] ];
            }

            $esQuery['query']['bool']['must'] = $mustClauses;
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

        $renderer->assign('query', $query);
        $renderer->assign('pages', $pages);
        $renderer->assign('contents', $contents);
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
