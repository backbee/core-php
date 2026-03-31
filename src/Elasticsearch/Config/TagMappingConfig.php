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
 * Class TagMappingConfig
 *
 * @author Djoudi Bensid <d.bensid@team-one.fr>
 */
class TagMappingConfig implements TagMappingConfigInterface
{
    /**
     * Return tag properties.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'analyzer' => 'autocomplete',
                'search_analyzer' => 'standard',
                'fielddata' => true,
            ],
            'source' => [
                'type' => 'keyword',
            ],
            'parents' => [
                'type' => 'keyword',
            ],
        ];
    }
}