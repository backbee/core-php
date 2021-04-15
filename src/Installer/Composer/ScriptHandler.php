<?php

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
        $filesystem->mirror($distSourceDir, $destDir, null, ['override' => true, 'delete' => true]);
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
        $filesystem->mirror($distSourceDir, $destDir, null, ['override' => true, 'delete' => false]);
    }
}
