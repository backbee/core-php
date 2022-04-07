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

use BackBee\Renderer\Renderer;
use Datetime;
use Exception;

/**
 * Class SchemaArticle
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaArticle implements SchemaInterface
{
    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * @var SchemaContext
     */
    private $context;

    /**
     * SchemaArticle constructor.
     *
     * @param SchemaContext $context
     */
    public function __construct(SchemaContext $context)
    {
        $this->context = $context;
        $this->renderer = $context->getApplication()->getRenderer();
    }

    /**
     * Returns the Article  Schema data.
     *
     * @return array $data The Article schema.
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function generate(): array
    {
        $cxData = $this->context->getData();
        $url = $this->renderer->getUri($cxData['url'], null, null, null, false);

        $data = [
            '@type' => 'Article',
            '@id' => $url . SchemaIds::ARTICLE_HASH,
            'name' => $cxData['title'],
            'description' => $cxData['abstract'],
            'articleBody' => $cxData['contents'],
            'headline' => substr($cxData['title'], 0, 110),
            'url' => $url,
            'isPartOf' => [
                '@id' => $url . SchemaIds::WEBPAGE_HASH,
            ],
            'mainEntityOfPage' => $url . SchemaIds::WEBPAGE_HASH,
            'publisher' => [
                '@id' => $this->renderer->getUri('/') . SchemaIds::ORGANIZATION_HASH,
            ],
            'author' => [
                '@id' => $this->renderer->getUri('/') . SchemaIds::ORGANIZATION_HASH,
            ],
            'dateCreated' => (new Datetime($cxData['created_at']))->format('c'),
            'dateModified' => (new Datetime($cxData['modified_at']))->format('c'),
            'datePublished' => (new Datetime($cxData['published_at']))->format('c'),
        ];

        return $this->addImage($data);
    }

    /**
     * Adds a article's image.
     *
     * @param array $data The Article schema.
     *
     * @return array $data The Article schema.
     */
    private function addImage(array $data): array
    {
        $cxData = $this->context->getData();

        $schemaId = $this->renderer->getUri($cxData['url']) . SchemaIds::PRIMARY_IMAGE_HASH;

        if (null === $cxData['image']) {
            $data['image'] = new SchemaImage($schemaId);

            return $data;
        }

        $schemaImage = new SchemaImage($schemaId);
        $data['image'] = $schemaImage->generate($this->renderer->getUri($cxData['image']['url']));

        return $data;
    }
}
