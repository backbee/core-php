<?php

namespace BackBeeCloud\Elasticsearch;

use BackBee\Event\Event;
use BackBee\Exception\BBException;
use BackBee\NestedNode\Page;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ElasticsearchListener
{
    /**
     * Occurs on `rest.controller.pagecontroller.deleteaction.postcall` to remove
     * the page document from Elasticsearch.
     *
     * @param Event $event
     *
     * @throws BBException
     */
    public static function onPageDeletePostcall(Event $event): void
    {
        $app = $event->getApplication();
        $page = $app->getRequest()->attributes->get('page');
        if (!($page instanceof Page)) {
            return;
        }

        $app->getContainer()->get('elasticsearch.manager')->deletePage($page);
    }
}
