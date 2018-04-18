<?php

namespace BackBeeCloud\Listener;

use BackBeeCloud\ClassContent\ClassContentOverrider;
use BackBee\Event\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ClassContentOverriderListener
{
    /**
     * @var ClassContentOverrider
     */
    private $classcontentOverrider;

    public function __construct(ClassContentOverrider $overrider)
    {
        $this->classcontentOverrider = $overrider;
    }

    public function onApplicationInit(Event $event)
    {
        if ($event->getTarget()->isRestored()) {
            return;
        }

        $this->classcontentOverrider->generate();
    }
}
