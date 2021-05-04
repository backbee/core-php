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
use BackBee\NestedNode\Page;

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
     * ElasticsearchQuery constructor.
     *
     * @param BBApplication $bbApp
     */
    public function __construct(BBApplication $bbApp)
    {
        $this->bbApp = $bbApp;
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

        $baseQuery['query']['bool']['should'] = array_map(static function($tag) {
            return [
                'match' => ['tags.raw' => strtolower($tag)],
            ];
        },$validTags);

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

        if ($page && $pageType = ($this->bbApp->getContainer()->get('cloud.page_type.manager')->findByPage($page))) {
            $mustClauses = [
                [
                    'match' => [
                        'type' => $pageType->uniqueName()
                    ]
                ]
            ];
        }

        if (null === $this->bbApp->getBBUserToken()) {
            $mustClauses[] = [
                'match' => [
                    'is_online' => true
                ]
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
}
