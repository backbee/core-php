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

namespace BackBee\KnowledgeGraph\Schema;

use BackBee\BBApplication;

/**
 * Class SchemaContext
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaContext
{
    /**
     * @var BBApplication
     */
    private $app;

    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $config;

    /**
     * SchemaContext constructor.
     *
     * @param BBApplication $app
     * @param array         $data
     * @param array         $config
     */
    public function __construct(BBApplication $app, array $data, array $config)
    {
        $this->app = $app;
        $this->data = $data;
        $this->config = $config;
    }

    /**
     * @return BBApplication
     */
    public function getApplication(): BBApplication
    {
        return $this->app;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
