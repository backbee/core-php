<?php

namespace BackBeeCloud\Listener;

use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use Exception;

/**
 * Class ElasticsearchListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ElasticsearchListener
{
    /**
     * Occurs on `rest.controller.pagecontroller.deleteaction.postcall` to remove
     * the page document from Elasticsearch.
     *
     * @param Event $event
     */
    public static function onPageDeletePostcall(Event $event): void
    {
        try {
            $page = $event->getApplication()->getRequest()->attributes->get('page');

            if (!($page instanceof Page)) {
                return;
            }

            $event->getApplication()->getContainer()->get('elasticsearch.manager')->deletePage($page);
        } catch (Exception $exception) {
            $event->getApplication()->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }
}
