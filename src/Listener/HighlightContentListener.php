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

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\Content\HighlightContent;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\ClassContent\Media\Video;
use BackBee\ClassContent\Revision;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Event\RendererEvent;
use DateTime;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class HighlightContentListener
{
    /**
     * Called on `rest.controller.classcontentcontroller.getaction.postcall` event.
     *
     * @param Event $event
     */
    public static function onPostCall(Event $event): void
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
     * @param RendererEvent $event
     *
     * @throws Exception
     */
    public static function onRender(RendererEvent $event): void
    {
        $app = $event->getApplication();
        $renderer = $event->getRenderer();
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

        if (false !== $param) {
            $shouldMatch = [];
            foreach ($param as $data) {
                $shouldMatch[] = ['match' => ['_id' => $data['id']]];
            }

            $pages = $app->getContainer()->get('elasticsearch.manager')->customSearchPage(
                [
                    'query' => [
                        'bool' => [
                            'should' => $shouldMatch,
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
                0,
                count($param),
                [],
                false
            );

            $pages = self::sortPagesByUids($param, $pages);

            foreach ($pages as $page) {
                $contents[] = self::renderPageFromRawData(
                    $page,
                    $block,
                    $renderer->getMode(),
                    $app
                );
            }
        }

        $renderer->assign('contents', $contents);
    }

    /**
     * Sort pages by uids.
     *
     * @param array $param
     * @param       $pages
     *
     * @return array
     */
    public static function sortPagesByUids(array $param, $pages): array
    {
        $uids = [];
        foreach ($param as $data) {
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

    /**
     * Render page form raw data.
     *
     * @param array                $pageRawData
     * @param AbstractClassContent $wrapper
     * @param                      $currentMode
     * @param BBApplication        $bbApp
     *
     * @return string
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public static function renderPageFromRawData(
        array $pageRawData,
        AbstractClassContent $wrapper,
        $currentMode,
        BBApplication $bbApp
    ): ?string {
        if (!($wrapper instanceof HighlightContent) && !($wrapper instanceof ContentAutoblock)) {
            return null;
        }

        $mode = '';
        $displayImage = $wrapper->getParamValue('display_image');
        if ($currentMode === 'rss') {
            $mode = '.rss';
        } elseif ($displayImage) {
            $mode = '.' . $wrapper->getParamValue('format');
        }
        $title = $wrapper->getParamValue('title_to_be_displayed') === 'first_heading' ?
            $pageRawData['_source']['first_heading'] : $pageRawData['_source']['title'];
        $abstract = null;
        if (null !== $abstractUid = $pageRawData['_source']['abstract_uid'] ?: null) {
            $abstract = self::getContentWithDraft(ArticleAbstract::class, $abstractUid, $bbApp);
            $abstract = $abstract ?: self::getContentWithDraft(Paragraph::class, $abstractUid, $bbApp);
            if (null !== $abstract) {
                $abstract = trim(preg_replace('#\s\s+#', ' ', preg_replace('#<[^>]+>#', ' ', $abstract->value)));
            }
        }

        $imageData = [
            'is_video_thumbnail' => false,
        ];

        if (null !== $mediaUid = $pageRawData['_source']['image_uid'] ?: null) {
            $image = null;
            $media = self::getContentWithDraft(AbstractClassContent::class, $mediaUid, $bbApp);
            if ($media instanceof Video) {
                $image = $media->thumbnail->image;
                $imageData['is_video_thumbnail'] = true;
            } elseif ($media instanceof Image) {
                $image = $media->image;
            }

            if (null !== $image) {
                $imageData = [
                        'uid' => $image->getUid(),
                        'url' => $image->path,
                        'title' => $image->getParamValue('title'),
                        'legend' => $image->getParamValue('description'),
                        'alt' => ($media instanceof Image)
                            ? $bbApp->getRenderer()->getImageAlternativeText($media, $title)
                            : $title,
                        'stat' => $image->getParamValue('stat'),
                    ] + $imageData;
            }
        }

        return $bbApp->getRenderer()->partial(
            sprintf('ContentAutoblock/item%s.html.twig', $mode),
            [
                'title' => $title,
                'abstract' => (string)$abstract,
                'tags' => array_map('ucfirst', $pageRawData['_source']['tags']),
                'url' => $pageRawData['_source']['url'],
                'is_online' => $pageRawData['_source']['is_online'],
                'publishing' => $pageRawData['_source']['published_at']
                    ? new DateTime($pageRawData['_source']['published_at'])
                    : null
                ,
                'image' => $imageData,
                'bg_image' => self::getItemBgImage($pageRawData, $bbApp),
                'display_abstract' => $wrapper->getParamValue('abstract'),
                'display_published_at' => $wrapper->getParamValue('published_at'),
                'reduce_title_size' => !$displayImage && !$wrapper->getParamValue('abstract'),
                'display_image' => $displayImage,
                'title_max_length' => $wrapper->getParamValue('title_max_length'),
                'abstract_max_length' => $wrapper->getParamValue('abstract_max_length'),
                'wrapper' => $wrapper,
            ]
        );
    }

    /**
     * Get content with draft.
     *
     * @param                  $classname
     * @param                  $uid
     *
     * @param BBApplication    $bbApp
     *
     * @return object|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected static function getContentWithDraft($classname, $uid, BBApplication $bbApp)
    {
        $content = $bbApp->getEntityManager()->find($classname, $uid);

        if (null !== $content && null !== ($bbToken = $bbApp->getBBUserToken())) {
            $draft = $bbApp->getEntityManager()->getRepository(Revision::class)->getDraft($content, $bbToken);
            $content->setDraft($draft);
        }

        return $content;
    }

    /**
     * Get Background image by page.
     *
     * @param array         $item
     * @param BBApplication $bbApp
     *
     * @return string|null
     */
    private static function getItemBgImage(array $item, BBApplication $bbApp): ?string
    {
        if (null !== $page = $bbApp->getEntityManager()->getRepository(Page::class)->find($item['_id'])) {
            foreach ($page->getContentSet()->first() as $cloudContentSet) {
                if (
                    $cloudContentSet instanceof CloudContentSet &&
                    null !== ($params = $cloudContentSet->getAllParams()) &&
                    (null !== $params['bg_image'] ?? null) &&
                    !empty($params['bg_image']['value'])
                ) {
                    $bgImage = $params['bg_image']['value'];
                    break;
                }
            }
        }

        return $bgImage ?? null;
    }
}
