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
use BackBeeCloud\ClassContent\ClassContentOverrider;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ClassContentOverriderListener
{
    /**
     * @var ClassContentOverrider
     */
    private $classContentOverrider;

    /**
     * @param \BackBeeCloud\ClassContent\ClassContentOverrider $overrider
     */
    public function __construct(ClassContentOverrider $overrider)
    {
        $this->classContentOverrider = $overrider;
    }

    /**
     * On application init.
     *
     * @param \BackBee\Event\Event $event
     */
    public function onApplicationInit(Event $event): void
    {
        if ($event->getTarget()->isRestored()) {
            return;
        }

        $this->classContentOverrider->generate();
    }
}
