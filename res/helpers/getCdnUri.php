<?php

namespace BackBee\Renderer\Helper;

use BackBeePlanet\GlobalSettings;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Renderer\Helper\AbstractHelper;
use BackBee\Site\Site;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getCdnUri extends AbstractHelper
{
    const CDN_SETTINGS_KEY = 'static_domain';

    /**
     * @var Site|null
     */
    protected $site;

    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $settings = (new GlobalSettings())->cdn();

        if (isset($settings[static::CDN_SETTINGS_KEY]) && false != $settings[static::CDN_SETTINGS_KEY]) {
            $this->site = new Site();
            $this->site->setServerName(str_replace('http://', '', $settings[static::CDN_SETTINGS_KEY]));
        }
    }

    public function __invoke($uri, $preserveScheme = false)
    {
        $url = $this->_renderer->getUri($uri, null, $this->site);

        return false === $preserveScheme ? preg_replace('~https?:~', '', $url) : $url;
    }
}
