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
