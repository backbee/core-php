<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\AbstractRenderer;
use BackBee\Site\Site;
use Exception;

/**
 * Class getCdnUri
 *
 * @package BackBee\Renderer\Helper
 *
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
     *
     * @throws Exception
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $settings = $renderer->getApplication()->getConfig()->getSection('cdn');

        if (isset($settings[static::CDN_SETTINGS_KEY]) && false !== $settings[static::CDN_SETTINGS_KEY]) {
            $this->site = new Site();
            $this->site->setServerName(str_replace('http://', '', $settings[static::CDN_SETTINGS_KEY]));
        }
    }

    /**
     * Invoke.
     *
     * @param      $uri
     * @param bool $preserveScheme
     *
     * @return string|string[]|null
     */
    public function __invoke($uri, bool $preserveScheme = false)
    {
        $url = $this->_renderer->getUri($uri, null, $this->site);

        return false === $preserveScheme ? preg_replace('~https?:~', '', $url) : $url;
    }
}
