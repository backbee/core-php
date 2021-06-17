<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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
                            'match' => [
                                'title' => [
                                    'query' => $term,
                                    'boost' => 5,
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'title.raw' => [
                                    'query' => $term,
                                    'boost' => 5,
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'title.folded' => [
                                    'query' => $term,
                                    'boost' => 5,
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'contents' => [
                                    'query' => $term,
                                    'boost' => 3,
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'contents.folded' => [
                                    'query' => $term,
                                    'boost' => 3,
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'tags' => [
                                    'query' => $term,
                                    'boost' => 2,
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'tags.raw' => [
                                    'query' => $term,
                                    'boost' => 2,
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'tags.folded' => [
                                    'query' => $term,
                                    'boost' => 2,
                                    'fuzziness' => 'AUTO',
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
                    'match' => ['tags.raw' => strtolower($tag)],
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

        if (null === $this->bbApp->getBBUserToken()) {
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
        ?string $searchIn,
        ?string $searchByTerm
    ): array {
        if ($searchByTerm === 'exact_term') {
            $baseQuery = $this->titleFilter->byExactTerm($baseQuery, $title);
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
            if (null !== $tag = $this->bbApp->getEntityManager()->getRepository(KeyWord::class)->find($tagId)) {
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
                                'tags.raw' => strtolower($tag->getKeyWord()),
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
        if (null === $type) {
            $baseQuery['query']['bool']['must_not'] = array_merge(
                $baseQuery['query']['bool']['must_not'] ?? [],
                array_filter(
                    array_map(
                        static function ($type) {
                            return false === $type->isProtected() ||
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
}
