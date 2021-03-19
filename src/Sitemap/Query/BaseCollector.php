<?php

namespace BackBeePlanet\Sitemap\Query;

use BackBee\Exception\BBException;
use BackBee\NestedNode\Page;
use BackBee\Utils\StringUtils;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use RuntimeException;

/**
 * Class BaseCollector
 *
 * A base collector to collect Page objects.
 *
 * Allowed discriminators are:
 *  * layout: pages will be categorized by layout,
 *  * section: pages will be categorized by their section,
 *  * year: pages will be categorized by their publication year,
 *  * index: numerator index
 *
 * @package BackBeePlanet\Sitemap\Query
 */
class BaseCollector extends AbstractCollector
{
    /**
     * Array of accepted URL discriminators.
     *
     * @var array;
     */
    protected $acceptedDiscriminators = ['layout', 'section', 'year', 'index'];

    /**
     * An array of arrays of pages indexed by their URLs.
     *
     * @var array
     */
    private $collection;

    /**
     * Returns the current query builder.
     *
     * @return QueryBuilder|object
     */
    public function getQueryBuilder()
    {
        if (!$this->getContainer()->has('core.sitemap.query_builder')) {
            throw new RuntimeException('Undefined sitemap query builder service.');
        }

        return $this->getContainer()->get('core.sitemap.query_builder');
    }

    /**
     * Collects pages and organizes them regarding discriminators.
     *
     * @param array $preset Optional, an array of preset values for discriminators.
     *
     * @return array An array of matching pages indexed by their sitemap URLs.
     */
    public function collect(array $preset = []): array
    {
        $this->collection = [];
        $this->setPreset($preset);

        $this->collectSitemaps(
            $this->getQueryBuilder()->reset(),
            $this->getUrlPattern(),
            $this->getDiscriminatorsFromPattern()
        );

        return $this->collection;
    }

    /**
     * Recursively collects sitemaps according to discriminators.
     *
     * @param QueryBuilder $pqb            The query to be executed.
     * @param string       $urlPattern     The URL pattern of sitemaps.
     * @param array        $discriminators The activated discriminators.
     *
     * @return int                         The number of new sitemaps collected.
     */
    private function collectSitemaps(QueryBuilder $pqb, string $urlPattern, array $discriminators): int
    {
        if (empty($discriminators)) {
            $this->collection[$urlPattern] = new Paginator($pqb->getQuery());

            return $this->collection[$urlPattern]->count();
        }

        $discriminator = array_shift($discriminators);
        $method = 'collectBy' . ucfirst($discriminator);

        return $this->$method($pqb, $urlPattern, $discriminators);
    }

    /**
     * Collects sitemaps by layout discriminator.
     *
     * @param QueryBuilder $pqb            The query to be executed.
     * @param string       $urlPattern     The URL pattern of sitemaps.
     * @param array        $discriminators The activated discriminators.
     *
     * @return int                         The number of new sitemaps collected.
     */
    private function collectByLayout(QueryBuilder $pqb, string $urlPattern, array $discriminators)
    {
        $count = 0;
        $preset = $this->preset['layout'] ?? null;
        $layouts = $pqb->getLayoutSelection($preset);

        foreach ($layouts as $layout) {
            $query = clone $pqb;
            $query->andLayoutIs($layout);
            $pattern = str_replace('{layout}', StringUtils::urlize($layout->getLabel()), $urlPattern);
            $count += $this->collectSitemaps($query, $pattern, $discriminators);
        }

        return $count;
    }

    /**
     * Collects sitemaps by section discriminator.
     *
     * @param QueryBuilder $pqb            The query to be executed.
     * @param string       $urlPattern     The URL pattern of sitemaps.
     * @param array        $discriminators The activated discriminators.
     *
     * @return int                         The number of new sitemaps collected.
     */
    private function collectBySection(QueryBuilder $pqb, string $urlPattern, array $discriminators): int
    {
        $count = 0;
        $preset = $this->preset['section'] ?? null;
        $sections = $pqb->getSectionSelection($preset);

        foreach ($sections as $section) {
            $query = clone $pqb;
            $query->andParentIs($section);
            $pattern = str_replace('{section}', StringUtils::urlize($section->getTitle()), $urlPattern);
            $count += $this->collectSitemaps($query, $pattern, $discriminators);
        }

        return $count;
    }

    /**
     * Collects sitemaps by year discriminator.
     *
     * @param QueryBuilder $pqb            The query to be executed.
     * @param string       $urlPattern     The URL pattern of sitemaps.
     * @param array        $discriminators The activated discriminators.
     *
     * @return int                         The number of new sitemaps collected.
     * @throws BBException
     */
    private function collectByYear(QueryBuilder $pqb, string $urlPattern, array $discriminators): int
    {
        $preset = $this->preset['year'] ?? null;
        if (null === $oldestPage = $pqb->getOldestPage($preset)) {
            return 0;
        }

        $count = 0;
        $minYear = (int)date('Y', $oldestPage->getModified()->getTimestamp());
        $currentYear = $preset ?: (int)date('Y');

        for ($year = $minYear; $year <= $currentYear; $year++) {
            $query = clone $pqb;
            $query->andYearIs($year);
            $pattern = str_replace('{year}', $year, $urlPattern);
            $count += $this->collectSitemaps($query, $pattern, $discriminators);
        }

        return $count;
    }

    /**
     * Collects sitemaps by index discriminator.
     *
     * @param QueryBuilder $pqb            The query to be executed.
     * @param string       $urlPattern     The URL pattern of sitemaps.
     * @param array        $discriminators The activated discriminators.
     *
     * @return integer                      The number of new sitemaps collected.
     */
    private function collectByIndex(QueryBuilder $pqb, string $urlPattern, array $discriminators): int
    {
        if (null === $limit = $this->getLimit('num_loc_per_page')) {
            $urlPattern = str_replace('{index}', 1, $urlPattern);

            return $this->collectSitemaps($pqb, $urlPattern, $discriminators);
        }

        $query = clone $pqb;
        $query->setMaxResults($limit);

        $total = 0;
        $preset = $this->preset['index'] ?? null;
        $index = $preset ? $preset - 1 : 0;
        $hasResult = true;

        while ($hasResult) {
            $query->setFirstResult($index * $limit);
            $index++;
            $pattern = str_replace('{index}', $index, $urlPattern);
            $total = $this->collectSitemaps($query, $pattern, $discriminators);
            $hasResult = null === $preset && ($total - ($limit * $index) > 0);
        }

        return $total;
    }
}
