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
    public static function onFlushContent(Event $event): void
    {
        // TODO: Implement onFlushContent() method.
    }

    /**
     * {@inheritDoc}
     */
    public static function onPreRemoveContent(Event $event): void
    {
        // TODO: Implement onPreRemoveContent() method.
    }
}