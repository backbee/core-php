<?php

namespace BackBeeCloud\Entity;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\Event\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentDuplicatePreSaveEvent extends Event
{
    public function __construct($target, $eventArgs = null)
    {
        if (!($target instanceof AbstractClassContent)) {
            throw new \InvalidArgumentException(sprintf(
                '%s first argument must be type of %s',
                __METHOD__,
                AbstractClassContent::class
            ));
        }

        parent::__construct($target, $eventArgs);
    }

    public function getContent()
    {
        return $this->getTarget();
    }
}
