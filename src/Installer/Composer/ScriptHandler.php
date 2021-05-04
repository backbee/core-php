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

namespace BackBee\Installer\Composer;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ScriptHandler
 *
 * @package BackBee\Installer\Composer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ScriptHandler
{
    /**
     * Moves cloud toolbar dist resources from vendor/ to web/.
     */
    public static function moveDistResources(): void
    {
        $filesystem = new Filesystem();
        $rootDir = getcwd();

        $distSourceDir = sprintf('%s/vendor/backbee/toolbar-dist/dist', $rootDir);

        if (!is_dir($distSourceDir)) {
            return;
        }

        $destDir = sprintf('%s/web/static/back', $rootDir);
        $filesystem->mkdir($destDir);
        $filesystem->mirror(
            $distSourceDir,
            $destDir,
            null, [
                'override' => true,
                'delete' => true
            ]
        );
    }

    /**
     * Moves config dist resources from vendor/backbee/core-php/res/dist to res/.
     */
    public static function moveConfigDistResources(): void
    {
        $filesystem = new Filesystem();
        $rootDir = getcwd();

        $distSourceDir = sprintf('%s/vendor/backbee/core-php/res/dist', $rootDir);

        if (!is_dir($distSourceDir)) {
            return;
        }

        $destDir = sprintf('%s/res/dist', $rootDir);
        $filesystem->mkdir($destDir);
        $filesystem->mirror(
            $distSourceDir,
            $destDir,
            null,
            [
                'override' => false,
                'delete' => false
            ]
        );
    }
}
