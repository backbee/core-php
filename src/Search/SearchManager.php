<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBeeCloud\Search;

use BackBee\NestedNode\Page;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Elasticsearch\ElasticsearchQuery;
use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\PageType\SearchResultType;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use function in_array;

/**
 * Class SearchManager
 *
 * @package BackBeeCloud\Search
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SearchManager extends AbstractSearchManager
{
    /**
     * @var MultiLangManager
     */
    private $multiLangManager;

    /**
     * @var ElasticsearchManager
     */
    private $elasticsearchManager;

    /**
     * @var ElasticsearchQuery
     */
    private $elasticsearchQuery;

    /**
     * SearchManager constructor.
     *
     * @param PageManager          $pageMgr
     * @param ContentManager       $contentMgr
     * @param EntityManager        $entityMgr
     * @param LoggerInterface      $logger
     * @param MultiLangManager     $multiLangManager
     * @param ElasticsearchManager $elasticsearchManager
     * @param ElasticsearchQuery   $elasticsearchQuery
     */
    public function __construct(
        PageManager $pageMgr,
        ContentManager $contentMgr,
        EntityManager $entityMgr,
        LoggerInterface $logger,
        MultiLangManager $multiLangManager,
        ElasticsearchManager $elasticsearchManager,
        ElasticsearchQuery $elasticsearchQuery
    ) {
        parent::__construct($pageMgr, $contentMgr, $entityMgr, $logger);
        $this->multiLangManager = $multiLangManager;
        $this->elasticsearchManager = $elasticsearchManager;
        $this->elasticsearchQuery = $elasticsearchQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function getResultPage($lang = null): ?Page
    {
        $uid = $this->getResultPageUid($lang);
        if (($page = $this->pageMgr->get($uid)) === null) {
            $page = $this->buildResultPage(
                $uid,
                'Search result',
                new SearchResultType(),
                $lang ? sprintf('/%s/search', $lang) : '/search',
                $lang
            );
        }

        return $page;
    }

    /**
     * Returns uid for page by tag result page.
     *
     * @param null|string $lang
     *
     * @return string
     */
    protected function getResultPageUid(?string $lang = null): string
    {
        return md5('search_result' . ($lang ? '_' . $lang : ''));
    }

    /**
     * Returns every page ordered by modified date (desc) of current application.
     *
     * @param array $criteria
     * @param       $start
     * @param       $limit
     * @param array $sort
     * @param bool  $formatResult
     *
     * @return ElasticsearchCollection
     */
    public function getBy(
        array $criteria,
        $start,
        $limit,
        array $sort = [],
        bool $formatResult = true
    ): ElasticsearchCollection {
        $query = [
            'query' => [
                'bool' => [
                    'must' => [],
                    'should' => [],
                ],
            ],
        ];

        $query['query']['bool']['must'][] = [
            'match' => [
                'source' => Page::SOURCE_TYPE,
            ],
        ];

        if ($this->multiLangManager->isActive()) {
            $query['query']['bool']['must_not'][] = [
                'match' => [
                    'url' => '/',
                ],
            ];
        }

        if ((($criteria['category'] ?? null) !== null) && $criteria['category'] !== 'none') {
            $query['query']['bool']['must'][] = [
                'match' => [
                    'category' => $criteria['category'],
                ],
            ];
        }

        $query = $this->elasticsearchQuery->getQueryToFilterByPageType($query, $criteria['type'] ?? null);

        if (($criteria['is_online'] ?? null) !== null && $criteria['is_online'] !== 'all') {
            $query = $this->elasticsearchQuery->getQueryToFilterByPageIsOnline($query, (bool)$criteria['is_online']);
        }

        if ($criteria['tags'] ?? false) {
            $query = $this->elasticsearchQuery->getQueryToFilterByTags($query, $criteria['tags']);
        }

        if ($criteria['lang'] && $criteria['lang'] !== 'all') {
            $query = $this->elasticsearchQuery->getQueryToFilterByLang($query, [$criteria['lang']]);
        }

        if ($criteria['title'] ?? false) {
            $query = $this->elasticsearchQuery->getQueryToFilterByTitle(
                $query,
                str_replace('%', '', $criteria['title']),
                $criteria['search_in'] ?? 'title',
                $criteria['search_by_term'] ?? 'exact_term'
            );
        }

        if (($criteria['has_draft_only'] ?? false) && (bool)$criteria['has_draft_only'] === true) {
            $query = $this->elasticsearchQuery->getQueryToFilterByPageWithDraftContents($query);
        }

        if (($criteria['created_at'] ?? false) ||
            ($criteria['modified_at'] ?? false) ||
            ($criteria['published_at'] ?? false)
        ) {
            $query = $this->elasticsearchQuery->getQueryToFilterByDate(
                $query,
                [
                    'created_at' => $criteria['created_at'] ?: null,
                    'modified_at' => $criteria['modified_at'] ?: null,
                    'published_at' => $criteria['published_at'] ?: null,
                ]
            );
        }

        if (($criteria['seo_index'] ?? null) !== null) {
            $query = $this->elasticsearchQuery->getQueryToFilterPageIndexedOrNot(
                $query,
                (bool)$criteria['seo_index']
            );
        }

        $sortValidAttrNames = [
            'modified_at',
            'created_at',
            'published_at',
            'type',
            'is_online',
            'category',
            'lang',
            'title',
        ];
        $sortValidOrder = [
            'asc',
            'desc',
        ];

        $orderBy = (empty($sort)) ? ['modified_at' => 'desc'] : $sort;
        foreach ($orderBy as $attr => $order) {
            if (!in_array($attr, $sortValidAttrNames, true)) {
                throw new InvalidArgumentException(sprintf('Pages are not sortable by %s .', $attr));
            }
            if (!in_array($order, $sortValidOrder, true)) {
                throw new InvalidArgumentException(sprintf("'%s' is not a valid order direction.", $order));
            }
            $query['sort'] = [
                $attr . ($attr === 'title' ? '.raw' : '') => [
                    'order' => $order,
                    'missing' => $order === 'desc' ? '_last' : '_first',
                ],
            ];
        }

        return $this->elasticsearchManager->customSearchPage($query, $start, $limit, [], $formatResult);
    }

    /**
     * Returns all pages with draft contents.
     *
     * @return ElasticsearchCollection
     */
    public function getPagesWithDraftContents(): ElasticsearchCollection
    {
        return $this->elasticsearchManager->customSearchPage(
            $this->elasticsearchQuery->getQueryToFilterByPageWithDraftContents([])
        );
    }
}
