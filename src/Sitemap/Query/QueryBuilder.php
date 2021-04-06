<?php

namespace BackBeePlanet\Sitemap\Query;

use BackBee\Exception\BBException;
use BackBee\NestedNode\Page;
use BackBee\NestedNode\Repository\PageQueryBuilder;
use BackBee\Site\Layout;
use BackBee\Site\Site;
use BackBee\Util\Numeric;
use DateInterval;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;

/**
 * Class QueryBuilder
 *
 * A query builder responsible for building DQL query strings for BaseCollector.
 *
 * @package BackBeePlanet\Sitemap\Query
 */
class QueryBuilder extends PageQueryBuilder
{
    /**
     * The selected site.
     *
     * @var Site
     */
    protected $site;

    /**
     * Initializes a new sitemap QueryBuilder for the given site.
     *
     * @param EntityManager $em   The EntityManager to use.
     * @param Site          $site The site for which sitemaps are generated.
     */
    public function __construct(EntityManager $em, Site $site)
    {
        parent::__construct($em);

        $this->site = $site;
        $this->reset();
    }

    /**
     * Resets the current query builder.
     *
     * @return QueryBuilder The current query builder.
     */
    public function reset(): QueryBuilder
    {
        $this->resetDQLParts(array_diff(array_keys($this->getDQLParts()), ['join']));
        $this->setParameters(new ArrayCollection());
        $this->select('p')->from(Page::class, 'p')->andSiteIs($this->site)->andIsOnline();

        return $this;
    }

    /**
     * Add query part to select pages with specific layouts identified by an
     * array of uids and/or labels.
     *
     * @param string[] $layoutsId An array of uids and/or labels to look for.
     *
     * @return QueryBuilder        The current query builder.
     * @throws BBException
     */
    public function andLayoutIn(array $layoutsId = []): QueryBuilder
    {
        $layouts = $this->findLayoutsFromIdsOrLabels($layoutsId);
        if (!empty($layouts)) {
            $this->andWhere($this->expr()->in($this->getAlias() . '_layout', $layouts));
        }

        return $this;
    }

    /**
     * Add a query part to select pages created after the max provided age.
     *
     * @param string $maxAge        Accept all the relative formats supported
     *                              by the parser used for strtotime().
     *
     * @return QueryBuilder         The current query builder.
     * @throws BBException
     */
    public function andIsPreviousTo(string $maxAge): QueryBuilder
    {
        if (!empty($maxAge)) {
            $now = new DateTime();
            if (false !== $date = $now->sub(DateInterval::createFromDateString($maxAge))) {
                $this
                    ->andWhere($this->getAlias() . '_modified > :maxAge')
                    ->setParameter('maxAge', $date->format('Y-m-d H:i:s'));
            }
        }

        return $this;
    }

    /**
     * Add a query part to select pages from a specific year.
     *
     * @param integer $year The limited year.
     *
     * @return QueryBuilder              The current query builder.
     *
     * @throws BBException
     */
    public function andYearIs(int $year): QueryBuilder
    {
        if (!Numeric::isPositiveInteger($year)) {
            throw new InvalidArgumentException(sprintf('`%s` is not a valid value for a year.', $year));
        }

        $first = new DateTime();
        $first->setDate($year, 1, 1)->setTime(0, 0);

        $last = new DateTime();
        $last->setDate($year, 12, 31)->setTime(23, 59, 59);

        $stringFirst = $this->expr()->literal($first->format('Y-m-d H:i:s'));
        $stringLast = $this->expr()->literal($last->format('Y-m-d H:i:s'));

        $this->andWhere($this->expr()->between($this->getAlias() . '._modified', $stringFirst, $stringLast));

        return $this;
    }

    /**
     * Returns a selection of layouts by optional preset.
     *
     * @param string|null $layout Optional, an uid or a label to look for.
     *
     * @return Layout[];
     */
    public function getLayoutSelection($layout = null): array
    {
        if (null !== $layout) {
            return $this->findLayoutsFromIdsOrLabels([$layout], false);
        }

        return $this->getEntityManager()->getRepository(Layout::class)->findBy(['_site' => $this->site]);
    }

    /**
     * Find layouts according to a provided array of uids or labels.
     *
     * @param string[] $layoutsId An array of uids and/or labels to look for.
     * @param boolean  $uidOnly   If true, returns an array of uids of Layout if false.
     *
     * @return array               An array of matching layouts.
     */
    private function findLayoutsFromIdsOrLabels(array $layoutsId = [], bool $uidOnly = true): array
    {
        $layouts = [];
        if (!empty($layoutsId)) {
            $repository = $this->getEntityManager()->getRepository(Layout::class);

            $layouts = array_merge(
                $repository->findBy(['_uid' => $layoutsId]),
                $repository->findBy(['_label' => $layoutsId])
            );
        }

        if (true === $uidOnly) {
            $layouts = array_map(
                static function (Layout $layout) {
                    return $layout->getUid();
                },
                $layouts
            );
        }

        return $layouts;
    }

    /**
     * Returns a selection of sections by optional preset.
     *
     * @param string|null $sectionUid Optional, an uid to look for.
     *
     * @return Page[]                  An array of matching pages.
     */
    public function getSectionSelection($sectionUid = null): array
    {
        $query = new $this($this->getEntityManager(), $this->site);
        $query->andIsSection()->addSearchCriteria(['p._level' => 1]);

        if (null !== $sectionUid) {
            $query->addSearchCriteria(['p._uid' => $sectionUid]);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Returns the oldest page in the site.
     *
     * @param integer|null $year Optional, a specific year to look for.
     *
     * @return Page|null          The oldest page in site if exists, null elsewhere.
     */
    public function getOldestPage($year = null): ?Page
    {
        $query = new $this($this->getEntityManager(), $this->site);
        $query->orderBy('p._modified', 'ASC')->setMaxResults(1);

        if (null !== $year) {
            $query->andYearIs($year);
        }

        return $query->getQuery()->getOneOrNullResult();
    }
}
