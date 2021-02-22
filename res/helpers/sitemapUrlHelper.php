<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\AbstractRenderer;
use Exception;

/**
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class sitemapUrlHelper extends AbstractHelper
{
    /**
     * Pattern excluded.
     *
     * @var array
     */
    private $excluded;

    /**
     * @var bool
     */
    private $forceUrlExtension;

    /**
     * sitemapUrlHelper constructor.
     *
     * @param AbstractRenderer $renderer
     *
     * @throws Exception
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $bbApp = $this->getRenderer()->getApplication();
        $this->excluded = $bbApp->getConfig()->getSitemapsConfig('excluded') ?? [];
        $this->forceUrlExtension = $bbApp->getConfig()->getParametersConfig('force_url_extension') ?? false;
    }

    /**
     * Is excluded.
     *
     * @param string $url
     *
     * @return bool
     */
    public function isExcluded(string $url): bool
    {
        return !(
            !empty($this->excluded) &&
            preg_match(
                '/w*(' . str_replace('/', '\/', implode('.*|', $this->excluded)) . ')/',
                $url
            )
        );
    }

    /**
     * Is url extension required.
     *
     * @param string $url
     *
     * @return string
     */
    public function isUrlExtensionRequired(string $url): string
    {
        return !$this->forceUrlExtension ? str_replace('.html', '', $url) : $url;
    }
}
