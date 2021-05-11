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
     * On flush.
     *
     * @param Event $event
     */
    public static function onFlush(Event $event): void;
}
