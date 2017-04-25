<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getCdnImageUrl extends getCdnUri
{
    const CDN_SETTINGS_KEY = 'image_domain';

    public function __invoke($uri, $preserveScheme = false)
    {
        $url = $this->_renderer->getUri(str_replace('/images', '', $uri), null, $this->site);

        return false === $preserveScheme ? preg_replace('~https?:~', '', $url) : $url;
    }
}
