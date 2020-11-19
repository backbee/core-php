<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\AbstractRenderer;
use BackBee\Site\Site;
use BackBeePlanet\GlobalSettings;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getCdnUri extends AbstractHelper
{
    public const CDN_SETTINGS_KEY = 'static_domain';

    /**
     * @var Site|null
     */
    protected $site;

    /**
     * getCdnUri constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $settings = (new GlobalSettings())->cdn();

        if (isset($settings[static::CDN_SETTINGS_KEY]) && false !== $settings[static::CDN_SETTINGS_KEY]) {
            $this->site = new Site();
            $this->site->setServerName(str_replace('http://', '', $settings[static::CDN_SETTINGS_KEY]));
        }
    }

    /**
     * @param       $uri
     * @param false $preserveScheme
     *
     * @return string|string[]|null
     */
    public function __invoke($uri, $preserveScheme = false)
    {
        $url = $this->_renderer->getUri($uri, null, $this->site);

        return false === $preserveScheme ? preg_replace('~https?:~', '', $url) : $url;
    }
}
