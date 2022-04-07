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

namespace BackBeeCloud\Elasticsearch;

use ArrayObject;
use BackBee\BBApplication;
use BackBee\Elasticsearch\Filter\TitleFilter;
use BackBee\NestedNode\KeyWord;
use BackBee\NestedNode\Page;
use BackBeeCloud\PageType\HomeType;
use BackBeeCloud\PageType\TypeManager;

/**
 * Class ElasticsearchQuery
 *
 * @package BackBeeCloud\Elasticsearch
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ElasticsearchQuery
{
    /**
     * @var BBApplication
     */
    private $bbApp;

    /**
     * @var TypeManager
     */
    private $typeManager;

    /**
     * @var TitleFilter
     */
    private $titleFilter;

    /**
     * ElasticsearchQuery constructor.
     *
     * @param BBApplication $bbApp
     * @param TypeManager   $typeManager
     * @param TitleFilter   $titleFilter
     */
    public function __construct(BBApplication $bbApp, TypeManager $typeManager, TitleFilter $titleFilter)
    {
        $this->bbApp = $bbApp;
        $this->typeManager = $typeManager;
        $this->titleFilter = $titleFilter;
    }

    /**
     * @param string $term
     *
     * @return array
     */
    public function getDefaultBooleanQuery(string $term): array
    {
        return [
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'match_phrase' => [
                                'title' => [
                                    'query' => $term,
                                    'boost' => 5,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'title.raw' => [
                                    'query' => $term,
                                    'boost' => 5,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'title.folded' => [
                                    'query' => $term,
                                    'boost' => 5,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'contents' => [
                                    'query' => $term,
                                    'boost' => 3,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'contents.folded' => [
                                    'query' => $term,
                                    'boost' => 3,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'tags' => [
                                    'query' => $term,
                                    'boost' => 2,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'tags.raw' => [
                                    'query' => $term,
                                    'boost' => 2,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'tags.folded' => [
                                    'query' => $term,
                                    'boost' => 2,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get a search query by tag.
     *
     * @param array|ArrayObject $baseQuery
     * @param array             $tags
     * @param bool              $withChildren
     *
     * @return array|ArrayObject
     */
    public function getSearchQueryByTag($baseQuery, array $tags, bool $withChildren = false)
    {
        $validTags = $this->bbApp->getContainer()->get('cloud.tag_manager')->getTagsValue($tags, $withChildren);

        if (empty($validTags)) {
            return $baseQuery;
        }

        $baseQuery['query']['bool']['should'] = array_map(
            static function ($tag) {
                return [
                    'match' => ['tags.raw' => $tag],
                ];
            },
            $validTags
        );

        $baseQuery['query']['bool']['minimum_should_match'] = 1;

        return $baseQuery;
    }

    /**
     * Get base query.
     *
     * @param Page|null $page
     * @param bool      $isArrayObject
     *
     * @return ArrayObject|array
     */
    public function getBaseQuery(?Page $page, bool $isArrayObject = false)
    {
        $esQuery = new ArrayObject(
            [
                'query' => [
                    'bool' => [],
                ],
            ]
        );

        // Building must clause
        $mustClauses = [];

        if ($page && $pageType = ($this->typeManager->findByPage($page))) {
            $mustClauses = [
                [
                    'match' => [
                        'type' => $pageType->uniqueName(),
                    ],
                ],
            ];
        }

        if ($this->bbApp->getBBUserToken() === null) {
            $mustClauses[] = [
                'match' => [
                    'is_online' => true,
                ],
            ];
        }

        $esQuery['query']['bool']['must'] = $mustClauses;

        if ($currentLang = ($this->bbApp->getContainer()->get('multilang_manager')->getCurrentLang())) {
            $esQuery['query']['bool']['must'][]['prefix'] = [
                'url' => sprintf('/%s/', $currentLang),
            ];
        }

        return $isArrayObject ? $esQuery : $esQuery->getArrayCopy();
    }

    /**
     * Get query to filter by title.
     *
     * @param array       $baseQuery
     * @param string      $title
     * @param string|null $searchIn
     * @param string|null $searchByTerm
     *
     * @return array
     */
    public function getQueryToFilterByTitle(
        array $baseQuery,
        string $title,
        string $searchIn = 'title',
        ?string $searchByTerm = null
    ): array {
        if ($searchByTerm === 'exact_term') {
            $baseQuery = $this->titleFilter->byExactTerm($baseQuery, $title, $searchIn);
        } else {
            $baseQuery = $this->titleFilter->byOperator($baseQuery, $title, $searchIn, $searchByTerm);
        }

        return $baseQuery;
    }

    /**
     * Get query to filter by tags.
     *
     * @param array  $baseQuery
     * @param string $tags
     *
     * @return array
     */
    public function getQueryToFilterByTags(array $baseQuery, string $tags): array
    {
        $validTags = null;

        foreach (explode(',', $tags) as $tagId) {
            if (($tag = $this->bbApp->getEntityManager()->getRepository(KeyWord::class)->find($tagId)) !== null) {
                $validTags[] = $tag;
            }
        }

        if ($validTags) {
            $baseQuery['query']['bool']['must'] = array_merge(
                $baseQuery['query']['bool']['must'] ?? [],
                array_map(
                    static function (KeyWord $tag) {
                        return [
                            'term' => [
                                'tags.raw' => $tag->getKeyWord(),
                            ],
                        ];
                    },
                    $validTags
                )
            );
        }

        return $baseQuery;
    }

    /**
     * Get query to filter by page type.
     *
     * @param array       $baseQuery
     * @param string|null $type
     *
     * @return array
     */
    public function getQueryToFilterByPageType(array $baseQuery, ?string $type): array
    {
        if ($type === null) {
            $baseQuery['query']['bool']['must_not'] = array_merge(
                $baseQuery['query']['bool']['must_not'] ?? [],
                array_filter(
                    array_map(
                        static function ($type) {
                            return $type->isProtected() === false ||
                            $type->uniqueName() === (new HomeType)->uniqueName() ?
                                null : ['match' => ['type' => $type->uniqueName()]];
                        },
                        array_values(
                            $this->typeManager->all()
                        )
                    )
                )
            );
        } else {
            $baseQuery['query']['bool']['must'][] = [
                'match' => [
                    'type' => $type,
                ],
            ];
        }

        return $baseQuery;
    }

    /**
     * Get query to filter by lang.
     *
     * @param array  $baseQuery
     * @param array  $languages
     * @param string $booleanQuery
     *
     * @return array
     */
    public function getQueryToFilterByLang(array $baseQuery, array $languages, string $booleanQuery = 'must'): array
    {
        $baseQuery['query']['bool'][$booleanQuery] = array_merge(
            $baseQuery['query']['bool'][$booleanQuery] ?? [],
            array_map(
                static function (string $lang) {
                    return [
                        'prefix' => [
                            'url' => sprintf('/%s/', $lang),
                        ],
                    ];
                },
                $languages
            )
        );

        return $baseQuery;
    }

    /**
     * Get query to filter by page is online.
     *
     * @param array $baseQuery
     * @param bool  $isOnline
     *
     * @return array
     */
    public function getQueryToFilterByPageIsOnline(array $baseQuery, bool $isOnline): array
    {
        $baseQuery['query']['bool']['must'] = array_merge(
            $baseQuery['query']['bool']['must'] ?? [],
            [
                [
                    'match' => [
                        'is_online' => $isOnline,
                    ],
                ],
            ]
        );

        return $baseQuery;
    }

    /**
     * Get query to filter by page with draft contents.
     *
     * @param array $baseQuery
     *
     * @return array
     */
    public function getQueryToFilterByPageWithDraftContents(array $baseQuery): array
    {
        $baseQuery['query']['bool']['must'] = array_merge(
            $baseQuery['query']['bool']['must'] ?? [],
            [
                [
                    'match' => [
                        'has_draft_contents' => true,
                    ],
                ],
            ]
        );

        return $baseQuery;
    }

    /**
     * Get query to filter by date.
     *
     * @param array $baseQuery
     * @param array $dates
     *
     * @return array
     */
    public function getQueryToFilterByDate(array $baseQuery, array $dates): array
    {
        foreach ($dates as $field => $date) {
            if ($date) {
                $date = (array)explode(',', $date);
                $baseQuery['query']['bool']['must'][] = [
                    'range' => [
                        $field => [
                            'gte' => $date[0] ?? null,
                            'lte' => $date[1] ?? null,
                        ],
                    ],
                ];
            }
        }

        return $baseQuery;
    }

    /**
     * Get a query to exclude pages by uid or url
     *
     * @param array $baseQuery
     * @param array $toExclude
     *
     * @return array
     */
    public function getQueryToExcludePagesByUidOrUrl(array $baseQuery, array $toExclude): array
    {
        $baseQuery['query']['bool']['must_not'] = array_merge(
            $baseQuery['query']['bool']['must_not'] ?? [],
            array_map(static function ($value) {
                return [
                    'match' => [
                        (strncmp($value, '/', 1) === 0 ? 'url' : '_id') => $value
                    ]
                ];
            }, $toExclude)
        );

        return $baseQuery;
    }

    /**
     * Get query to filter page indexed or not.
     *
     * @param array $baseQuery
     * @param bool  $isIndexed
     *
     * @return array
     */
    public function getQueryToFilterPageIndexedOrNot(array $baseQuery, bool $isIndexed): array
    {
        $baseQuery['query']['bool']['must'] = array_merge(
            $baseQuery['query']['bool']['must'] ?? [],
            [
                [
                    'match' => [
                        'seo_index' => $isIndexed,
                    ],
                ],
            ]
        );

        return $baseQuery;
    }
}
