<?php

namespace BackBeeCloud\Listener;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\ClassContent\Content\HighlightContent;
use BackBee\ClassContent\Media\Video;
use BackBee\ClassContent\Revision;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\Event\Event;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Security\Token\BBUserToken;
use Doctrine\ORM\EntityManager;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class HighlightContentListener
{
    /**
     * Called on `rest.controller.classcontentcontroller.getaction.postcall` event.
     *
     * @param  Event $event
     */
    public static function onPostCall(Event $event)
    {
        $app = $event->getApplication();
        $response = $event->getResponse();
        $data = json_decode($response->getContent(), true);
        $pageMgr = $app->getContainer()->get('cloud.page_manager');

        if ($data['type'] === 'Content/HighlightContent') {
            $contentParameters = $data['parameters']['content'];
            if (isset($contentParameters['value']['id'])) {
                $page = $pageMgr->get($contentParameters['value']['id']);
                if (null !== $page) {
                    $contentParameters['value']['title'] = $page->getTitle();
                }
            }

            $data['parameters']['content'] = $contentParameters;

            $response->setContent(json_encode($data));
        }
    }

    /**
     * Called on `content.highlightcontent.render` event.
     *
     * @param  RendererEvent  $event
     */
    public static function onRender(RendererEvent $event)
    {
        $app = $event->getApplication();
        $renderer = $event->getRenderer();
        $pageMgr = $app->getContainer()->get('cloud.page_manager');
        $block = $event->getTarget();

        $page = null;
        $content = null;

        $param = $block->getParamValue('content');
        $contents = [];

        if (isset($param['id'])) {
            $id = $param['id'];
            $param = [];

            $param[] = ['id' => $id];
        }

        if (false != $param) {
            $shouldMatch = [];
            foreach ($param as $data) {
                $shouldMatch[] = [ 'match' => [ '_id' => $data['id'] ] ];
            }

            $pages = $app->getContainer()->get('elasticsearch.manager')->customSearchPage([
               'query' => [
                   'bool' => [
                       'should' => $shouldMatch,
                       'minimum_should_match' => 1,
                   ],
               ],
            ], 0, count($param), [], false);

            $pages = self::sortPagesByUids($param, $pages);

            foreach ($pages as $page) {
                $contents[] = self::renderPageFromRawData($page, $block, $renderer->getMode(), $app);
            }
        }

        $renderer->assign('contents', $contents);
    }

    public static function sortPagesByUids(array $param, $pages)
    {
        $uids = [];
        foreach($param as $data) {
            $uids[] = $data['id'];
        }

        $sorted = [];
        $positions = array_flip($uids);
        foreach ($pages as $page) {
            $sorted[$positions[$page['_id']]] = $page;
        }

        ksort($sorted);

        return array_values($sorted);
    }


    public static function renderPageFromRawData(array $pageRawData, AbstractClassContent $wrapper, $currentMode, BBApplication $app)
    {
        if (!($wrapper instanceof HighlightContent) && !($wrapper instanceof ContentAutoblock)) {
            return;
        }

        $mode = '';
        $displayImage = $wrapper->getParamValue('display_image');
        if ($currentMode === 'rss') {
            $mode = '.rss';
        } elseif ($displayImage) {
            $mode = '.' . $wrapper->getParamValue('format');
        }

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

        $imageData = [
            'is_video_thumbnail' => false,
        ];
        if (false != $mediaUid = $pageRawData['_source']['image_uid']) {
            $image = null;
            $media = self::getContentWithDraft(AbstractClassContent::class, $mediaUid, $entyMgr, $bbtoken);
            if ($media instanceof Video) {
                $image = $media->thumbnail->image;
                $imageData['is_video_thumbnail'] = true;
            } elseif ($media instanceof Image) {
                $image = $media->image;
            }

            if (null !== $image) {
                $imageData = [
                    'uid'    => $image->getUid(),
                    'url'    => $image->path,
                    'title'  => $image->getParamValue('title'),
                    'legend' => $image->getParamValue('description'),
                    'stat'   => $image->getParamValue('stat'),
                ] + $imageData;
            }
        }

        return $app->getRenderer()->partial(sprintf('ContentAutoblock/item%s.html.twig', $mode), [
            'title'                => $pageRawData['_source']['title'],
            'abstract'             => (string) $abstract,
            'tags'                 => array_map('ucfirst', $pageRawData['_source']['tags']),
            'url'                  => $pageRawData['_source']['url'],
            'is_online'            => $pageRawData['_source']['is_online'],
            'publishing'           => $pageRawData['_source']['published_at']
                ? new \DateTime($pageRawData['_source']['published_at'])
                : null
            ,
            'image'                => $imageData,
            'display_abstract'     => $wrapper->getParamValue('abstract'),
            'display_published_at' => $wrapper->getParamValue('published_at'),
            'reduce_title_size'    => !$displayImage && !$wrapper->getParamValue('abstract'),
            'display_image'        => $displayImage,
            'title_max_length'     => $wrapper->getParamValue('title_max_length'),
            'abstract_max_length'  => $wrapper->getParamValue('abstract_max_length'),
            'autoblock_id'         => $wrapper instanceof ContentAutoblock
                ? ContentAutoblockListener::getAutoblockId($wrapper)
                : null
            ,
            'wrapper'              => $wrapper,
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
