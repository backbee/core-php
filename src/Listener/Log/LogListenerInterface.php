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

namespace BackBee\Listener\Log;

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Controller\Event\PreRequestEvent;

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
     * On post action post call.
     *
     * @param PostResponseEvent $event
     */
    public static function onPostActionPostCall(PostResponseEvent $event): void;

    /**
     * On put action post call.
     *
     * @param PostResponseEvent $event
     */
    public static function onPutActionPostCall(PostResponseEvent $event): void;

    /**
     * On delete action pre call.
     *
     * @param PreRequestEvent $event
     */
    public static function onDeleteActionPreCall(PreRequestEvent $event): void;
}
