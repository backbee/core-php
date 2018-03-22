<?php

namespace BackBeeCloud\ThemeColor;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ThemeColorManagerFactory
{
    const THEME_COLOR_SERVICE_TAG = 'theme_color';

    public static function createThemeColorManager(ContainerBuilder $dic)
    {
        $themes = [];
        foreach ($dic->findTaggedServiceIds(self::THEME_COLOR_SERVICE_TAG) as $serviceId => $data) {
            if ($dic->has($serviceId)) {
                $themes[] = $dic->get($serviceId);
            }
        }

        return new ThemeColorManager($themes);
    }
}
