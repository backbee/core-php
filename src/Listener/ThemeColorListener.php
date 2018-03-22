<?php

namespace BackBeeCloud\Listener;

use BackBee\Renderer\Event\RendererEvent;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ThemeColorListener
{
    public static function onPageRender(RendererEvent $event)
    {
        $cssGenerator = $event->getApplication()->getContainer()->get('cloud.color_panel.css_generator');

        $event->getRenderer()->addStylesheet(
            $event->getApplication()->getRouting()->getUrlByRouteName(
                'api.color_panel.get_color_panel_css',
                [
                    'hash' => $cssGenerator->getCurrentHash(),
                ]
            )
        );
    }
}
