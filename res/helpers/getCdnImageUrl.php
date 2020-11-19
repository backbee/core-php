<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getCdnImageUrl extends getCdnUri
{
    /**
     * Cdn settings key.
     */
    public const CDN_SETTINGS_KEY = 'image_domain';

    /**
     * @param       $uri
     * @param false $preserveScheme
     *
     * @return string|null
     */
    public function __invoke($uri, $preserveScheme = false): ?string
    {
        if ('' === $uri) {
            return null;
        }

        $path = parse_url($uri, PHP_URL_PATH);

        $url = $this->_renderer->getUri(
            str_replace(
                '/images',
                '',
                false === strpos($path, '/') ? '/' . $path : $path
            ),
            null,
            $this->site
        );

        return false === $preserveScheme ? preg_replace('~https?:~', '', $url) : $url;
    }
}
