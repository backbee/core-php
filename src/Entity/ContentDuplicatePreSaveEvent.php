<?php

/*
 * Copyright (c) 2022 Obione
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
