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

namespace BackBee\Listener;

use App\Helper\StandaloneHelper;
use BackBee\Event\Event;

/**
 * Class AppListener
 *
 * @package BackBee\Listener
 */
class AppListener
{
    /**
     * On application init.
     *
     * @param Event $event
     */
    public static function onApplicationInit(Event $event): void
    {
        $app = $event->getTarget();

        if ($app->isRestored()) {
            return;
        }

        $app->getRenderer()->addHelperDir(StandaloneHelper::resDir() . DIRECTORY_SEPARATOR . 'helpers');
        $app->getRenderer()->addLayoutDir(StandaloneHelper::resDir() . DIRECTORY_SEPARATOR . 'layouts');
        $app->getRenderer()->addScriptDir(StandaloneHelper::resDir() . DIRECTORY_SEPARATOR . 'views');
        $app->getRenderer()->addScriptDir(StandaloneHelper::resDir() . DIRECTORY_SEPARATOR . 'templates');
    }

    /**
     * On load class content.
     *
     * @param \BackBee\Event\Event $event
     */
    public static function onLoadClassContent(Event $event): void
    {
        $app = $event->getTarget();

        if ($app->isRestored()) {
            return;
        }

        $app->unshiftClassContentDir(StandaloneHelper::resDir() . '/classcontents');
    }
}
