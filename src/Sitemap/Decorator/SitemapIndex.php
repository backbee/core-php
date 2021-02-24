<?php

namespace BackBeePlanet\Sitemap\Decorator;

use DateTime;
use Exception;

/**
 * Class SitemapIndex
 *
 * Decorator for sitemap index.
 *
 * @see https://www.sitemaps.org/protocol.html#index
 *
 * @package BackBeePlanet\Sitemap\Decorator
 */
class SitemapIndex extends AbstractDecorator
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
        $locations = [];

        foreach ($this->getCollector()->collect($preset) as $sitemap) {
            foreach ($sitemap as $location) {
                $locations[] = [
                    'loc' => str_replace('.xml', '.xml.gz', $location),
                    'lastmod' => new DateTime(),
                ];
            }
        }

        return [
            $this->getCollector()->getUrlPattern() => [
                'lastmod' => new DateTime(),
                'urlset' => $this->getRenderer()->partial(
                    'Sitemap/SitemapIndex.html.twig',
                    array_merge($params, ['locations' => $locations])
                ),
            ],
        ];
    }
}
