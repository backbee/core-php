<?php
/*
 * Copyright (c) 2026 Obione
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

namespace BackBee\Elasticsearch\Config;

/**
 * Class PageMappingConfig
 *
 * @author Djoudi Bensid <d.bensid@team-one.fr>
 */
class PageMappingConfig implements PageMappingConfigInterface
{
    /**
     * Return page properties.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return [
            'title' => [
                'type' => 'text',
                'fields' => [
                    'raw' => [
                        'type' => 'keyword',
                    ],
                    'folded' => [
                        'type' => 'text',
                        'analyzer' => 'std_folded',
                    ],
                ],
            ],
            'first_heading' => [
                'type' => 'text',
                'fields' => [
                    'raw' => [
                        'type' => 'keyword',
                    ],
                    'folded' => [
                        'type' => 'text',
                        'analyzer' => 'std_folded',
                    ],
                ],
            ],
            'abstract_uid' => [
                'type' => 'keyword',
            ],
            'url' => [
                'type' => 'keyword',
            ],
            'image_uid' => [
                'type' => 'keyword',
            ],
            'contents' => [
                'type' => 'text',
                'fields' => [
                    'folded' => [
                        'type' => 'text',
                        'analyzer' => 'std_folded',
                    ],
                ],
            ],
            'tags' => [
                'type' => 'text',
                'fields' => [
                    'raw' => [
                        'type' => 'keyword',
                    ],
                    'folded' => [
                        'type' => 'text',
                        'analyzer' => 'std_folded',
                    ],
                ],
            ],
            'has_draft_contents' => [
                'type' => 'boolean',
            ],
            'created_at' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ],
            'modified_at' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ],
            'published_at' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ],
            'type' => [
                'type' => 'keyword',
            ],
            'is_online' => [
                'type' => 'boolean',
            ],
            'is_pullable' => [
                'type' => 'boolean',
            ],
            'category' => [
                'type' => 'keyword',
            ],
            'source' => [
                'type' => 'keyword',
            ],
            'lang' => [
                'type' => 'keyword',
            ],
            'level' => [
                'type' => 'integer',
            ],
            'state' => [
                'type' => 'integer',
            ],
            'seo_index' => [
                'type' => 'boolean',
            ],
            'seo_follow' => [
                'type' => 'boolean',
            ],
        ];
    }
}