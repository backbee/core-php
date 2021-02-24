<?php

namespace BackBeePlanet\Sitemap\Query;

use BackBee\Util\Doctrine\SettablePaginator;
use BackBeePlanet\Listener\SitemapListener;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Class IndexCollector
 *
 * A collector of sitemaps to build SitemapIndex.
 *
 * @package BackBeePlanet\Sitemap\Query
 */
class IndexCollector extends AbstractCollector
{
    /**
     * An array of available collectors.
     *
     * @var CollectorInterface[]
     */
    private $collectors;

    /**
     * Collects objects and organizes them regarding discriminators.
     *
     * @param array $preset Optional, an array of preset values for discriminators.
     *
     * @return Paginator[]         An array of matching sitemaps indexed by their sitemap URLs.
     */
    public function collect(array $preset = array()): array
    {
        $locations = [];

        foreach ($this->getCollectors() as $collector) {
            $sitemaps = $collector->collect($preset);
            $locations = array_merge($locations, array_keys($sitemaps));
        }

        $paginator = new SettablePaginator(new Query($this->getContainer()->get('em')));
        $paginator->setResult($locations)->setCount(count($locations));

        return [$this->getUrlPattern() => $paginator];
    }

    /**
     * Returns the available collectors (except itself).
     *
     * @return CollectorInterface[]
     */
    private function getCollectors(): array
    {
        if (null === $this->collectors) {
            $this->collectors = [];

            $ids = $this->getContainer()->findTaggedServiceIds(SitemapListener::$DECORATOR_TAG);
            foreach (array_keys($ids) as $id) {
                if ($this->getContainer()->get($id)->getCollector() === $this) {
                    continue;
                }

                $this->collectors[$id] = $this->getContainer()->get($id)->getCollector();
            }
        }

        return $this->collectors;
    }
}
