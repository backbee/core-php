<?php

namespace BackBee\Listener\Log;

use BackBee\Event\Event;

/**
 * Class TagLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class TagLogListener implements LogListenerInterface
{
    /**
     * {@inheritDoc}
     */
    public static function onFlush(Event $event): void
    {
        // TODO: Implement onFlush() method.
    }

    /**
     * {@inheritDoc}
     */
    public static function onRemove(Event $event): void
    {
        // TODO: Implement onRemove() method.
    }
}
