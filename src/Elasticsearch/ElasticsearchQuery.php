<?php

namespace BackBeeCloud\Elasticsearch;

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
}
