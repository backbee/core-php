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

namespace BackBee\Elasticsearch\Filter;

/**
 * Class TitleFilter
 *
 * @package BackBee\Elasticsearch\Filter
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class TitleFilter
{
    /**
     * Filter by exact term
     *
     * @param array  $baseQuery
     * @param string $title
     * @param string $searchIn
     *
     * @return array
     */
    public function byExactTerm(array $baseQuery, string $title, string $searchIn): array
    {
        $baseQuery['query']['bool']['must'] = array_merge(
            $baseQuery['query']['bool']['must'] ?? [],
            [
                [
                    'match_phrase' => [
                        ($searchIn === 'content' ? 'contents' : 'title') . '.folded' => $title,
                    ],
                ],
            ]
        );

        return $baseQuery;
    }

    /**
     * Filter by operator
     *
     * @param array       $baseQuery
     * @param string      $title
     * @param string|null $searchIn
     * @param string|null $searchByTerm
     *
     * @return array
     */
    public function byOperator(array $baseQuery, string $title, ?string $searchIn, ?string $searchByTerm): array
    {
        $matchPart = [
            'query' => $title,
            'operator' => $searchByTerm === 'all_term' ? 'and' : 'or',
        ];

        $baseQuery['query']['bool']['minimum_should_match'] = 1;
        $baseQuery['query']['bool']['should'] = array_merge(
            $baseQuery['query']['bool']['should'] ?? [],
            $this->{'In' . ucfirst($searchIn) . 'Field'}($matchPart)
        );

        return $baseQuery;
    }

    /**
     * Search term in content field.
     *
     * @param array $matchPart
     *
     * @return array
     */
    public function InContentField(array $matchPart): array
    {
        return [
            [
                'match' => [
                    'contents' => $matchPart,
                ],
            ],
            [
                'match' => [
                    'contents.folded' => $matchPart,
                ],
            ],
        ];
    }

    /**
     * Search term in title field.
     *
     * @param array $matchPart
     *
     * @return array
     */
    public function InTitleField(array $matchPart): array
    {
        return [
            [
                'match' => [
                    'title' => $matchPart,
                ],
            ],
            [
                'match' => [
                    'title.raw' => $matchPart,
                ],
            ],
            [
                'match' => [
                    'title.folded' => $matchPart,
                ],
            ],
        ];
    }
}
