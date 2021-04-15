<?php

namespace BackBee\Listener\Log;

use BackBee\Event\Event;

/**
 * Interface LogListenerInterface
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
interface LogListenerInterface
{
    /**
     * On flush content.
     *
     * @param Event $event
     */
    public static function onFlushContent(Event $event): void;

    /**
     * On pre remove content.
     *
     * @param Event $event
     */
    public static function onPreRemoveContent(Event $event): void;
}