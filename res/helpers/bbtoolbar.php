<?php

namespace BackBee\Renderer\Helper;

use Exception;

/**
 * Class bbtoolbar
 *
 * @package BackBee\Renderer\Helper
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class bbtoolbar extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @return string
     * @throws Exception
     */
    public function __invoke(): string
    {
        $settings = $this->getRenderer()->getApplication()->getConfig()->getSection('cdn');

        return $this->getRenderer()->partial(
            'common/toolbar.html.twig',
            [
                'appJsUrl' => $settings['app_js_url'],
                'appCssUrl' => $settings['app_css_url'],
                'appRteUrl' => $settings['app_rte_url'],
                'imageDomain' => $settings['image_domain'],
            ]
        );
    }
}
