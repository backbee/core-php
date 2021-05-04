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

namespace BackBee\KnowledgeGraph\Schema;

/**
 * Class SchemaImage
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaImage
{
    /**
     * @var string
     */
    private $schemaId;

    /**
     * SchemaImage constructor.
     *
     * @param $schemaId
     */
    public function __construct($schemaId)
    {
        $this->schemaId = $schemaId;
    }

    /**
     * Returns the Organization Schema data.
     *
     * @param string $url
     *
     * @return array $data The Organization schema.
     */
    public function generate(string $url): array
    {
        return [
            '@type' => 'ImageObject',
            '@id' => $this->schemaId,
            'url' => $url,
        ];
    }
}
