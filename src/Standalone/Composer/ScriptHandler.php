<?php

namespace BackBeePlanet\Standalone\Composer;

use BackBeePlanet\Standalone\StandaloneHelper;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ScriptHandler
 *
 * @package BackBeePlanet\Standalone\Composer
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ScriptHandler
{
    /**
     * Moves cloud toolbar dist resources from vendor/ to web/.
     */
    public static function moveDistResources(): void
    {
        $rootDir = getcwd();

        $distSourceDir = sprintf('%s/vendor/backbee/toolbar-dist/dist', $rootDir);

        if (!is_dir($distSourceDir)) {
            return;
        }

        $destDir = sprintf('%s/web/static/back', $rootDir);
        StandaloneHelper::mkdirOnce($destDir);

        (new Filesystem())->mirror($distSourceDir, $destDir, null, ['override' => true, 'delete' => true]);
    }
}
