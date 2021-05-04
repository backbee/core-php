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

use BackBee\Renderer\Renderer;
use Datetime;
use Exception;

/**
 * Class SchemaWebPage
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaWebPage implements SchemaInterface
{
    /**
     * @var SchemaContext
     */
    private $context;

    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * SchemaWebPage constructor.
     *
     * @param SchemaContext $context
     */
    public function __construct(SchemaContext $context)
    {
        $this->context = $context;
        $this->renderer = $context->getApplication()->getRenderer();
    }

    /**
     * Returns the WebPage  Schema data.
     *
     * @return array $data The WebPage schema.
     * @throws Exception
     */
    public function generate(): array
    {
        $app = $this->context->getApplication();
        $cxData = $this->context->getData();

        return [
            '@type' => 'WebPage',
            '@id' => $this->renderer->getUri($cxData['url']) . SchemaIds::WEBPAGE_HASH,
            'name' => $cxData['title'],
            'url' => $this->renderer->getUri($cxData['url']),
            'isPartOf' => [
                '@id' => $this->renderer->getUri('/') . SchemaIds::WEBSITE_HASH,
            ],
            'datePublished' => (new Datetime($cxData['published_at']))->format('c'),
            'dateModified' => (new Datetime($cxData['modified_at']))->format('c'),
        ];
    }
}
