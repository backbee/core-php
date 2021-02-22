<?php

namespace BackBeePlanet\Sitemap\Decorator;

use DateTime;
use Exception;

/**
 * Class UrlSet
 *
 * Decorator for sitemaps
 *
 * @see https://www.sitemaps.org/protocol.html
 *
 * @package BackBeePlanet\Sitemap\Decorator
 */
class UrlSet extends AbstractDecorator
{
    /**
     * Renders every sitemap collected.
     *
     * @param array $preset Optional, an array of preset values for discriminators.
     * @param array $params Optional rendering parameters.
     *
     * @return array         An array of rendered sitemaps indexed by their URLs.
     * @throws Exception
     */
    public function render(array $preset = [], array $params = []): array
    {
        $results = [];

        foreach ($this->getCollector()->collect($preset) as $url => $locations) {
            $results[$url] = [
                'lastmod' => new DateTime(),
                'urlset' => $this->getRenderer()->partial(
                    'Sitemap/UrlSet.html.twig',
                    array_merge($params, ['locations' => $locations])
                ),
            ];
        }

        return $results;
    }
}
