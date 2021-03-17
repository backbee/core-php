<?php

namespace BackBeeCloud\Listener;

use BackBee\ClassContent\AbstractContent;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBeeCloud\PageType\TypeManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageTypeListener
{
    public static function onPostload(Event $event)
    {
        $pageType = $event->getTarget();
        $typeMgr = $event->getApplication()->getContainer()->get('cloud.page_type.manager');

        if ($type = ($typeMgr->find($pageType->getTypeName()) ?: $typeMgr->getDefaultType())) {
            $pageType->setType($type);
        }
    }

    public static function onGetCategoryPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        $data = self::filterHiddenCategories(json_decode($response->getContent(), true));
        self::cleanCategoriesResponse($response, $data);

        $querybag = $event->getApplication()->getRequest()->query;
        if (!$querybag->has('page_uid')) {
            return;
        }

        $app = $event->getApplication();
        $entyMgr = $app->getEntityManager();
        if (false == $page = $entyMgr->find(Page::class, $querybag->get('page_uid'))) {
            return;
        }

        $data = self::filterExclusiveContent($page, $app->getContainer()->get('cloud.page_type.manager'), $data);
        self::cleanCategoriesResponse($response, $data);
    }

    protected static function filterHiddenCategories(array $categories)
    {
        $result = [];
        foreach ($categories as $category) {
            $visibleContents = [];
            foreach ($category['contents'] as $content) {
                if ($content['visible']) {
                    $visibleContents[] = $content;
                }
            }

            if (false != $visibleContents) {
                $category['contents'] = $visibleContents;
                $result[] = $category;
            }
        }

        return $result;
    }

    protected static function filterExclusiveContent(Page $page, TypeManager $typeMgr, array $categories)
    {
        $currentType = $typeMgr->findByPage($page);
        $toIgnore = [];
        foreach ($typeMgr->all() as $type) {
            if ($currentType === $type) {
                continue;
            }

            $toIgnore = array_merge($toIgnore, $type->exclusiveClassContents());
        }

        $result = [];
        foreach ($categories as $category) {
            $visibleContents = [];
            foreach ($category['contents'] as $content) {
                if (in_array(AbstractContent::getClassnameByContentType($content['type']), $toIgnore)) {
                    continue;
                }

                $visibleContents[] = $content;
            }

            if (false != $visibleContents) {
                $category['contents'] = $visibleContents;
                $result[] = $category;
            }
        }

        return $result;
    }

    protected static function cleanCategoriesResponse(Response $response, $data)
    {
        $response->setContent(json_encode($data));
        $response->headers->set('Content-Range', '0-' . (count($data) - 1) . '/' . count($data));
    }
}
