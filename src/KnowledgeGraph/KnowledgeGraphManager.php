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

namespace BackBee\KnowledgeGraph;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\KnowledgeGraph\Schema\SchemaContext;
use BackBee\KnowledgeGraph\Schema\SchemaOrganization;
use BackBee\KnowledgeGraph\Schema\SchemaWebPage;
use BackBee\KnowledgeGraph\Schema\SchemaWebSite;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Exception\RendererException;
use BackBee\Renderer\Renderer;
use BackBeeCloud\Controller\SearchEngineController;
use BackBeeCloud\Page\PageFromRawData;
use BackBeeCloud\UserPreference\UserPreferenceManager;
use Exception;
use ReflectionClass;
use ReflectionException;
use function in_array;
use function is_array;

/**
 * Class KnowledgeGraphManager
 *
 * @package BackBee\KnowledgeGraph
 *
 * @author  Michel Baptista <michel.baptista@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class KnowledgeGraphManager
{
    /**
     * @var BBApplication
     */
    private $app;

    /**
     * @var UserPreferenceManager
     */
    private $userPreferenceManager;

    /**
     * @var array
     */
    private $config;

    /**
     * @var SchemaContext
     */
    private $context;

    /**
     * @var PageFromRawData
     */
    private $pageFromRawData;

    /**
     * @var SeoMetadataManager
     */
    private $seoMetadataManager;

    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * @var array
     */
    private $userPreferenceValues;

    /**
     * KnowledgeGraphManager constructor.
     *
     * @param BBApplication $app
     * @param UserPreferenceManager $userPreferenceManager
     * @param Config $config
     * @param PageFromRawData $pageFromRawData
     * @param SeoMetadataManager $seoMetadataManager
     */
    public function __construct(
        BBApplication $app,
        UserPreferenceManager $userPreferenceManager,
        Config $config,
        PageFromRawData $pageFromRawData,
        SeoMetadataManager $seoMetadataManager
    ) {
        $this->app = $app;
        $this->renderer = $app->getRenderer();
        $this->userPreferenceManager = $userPreferenceManager;
        $this->config = $config->getSection('knowledge_graph');
        $this->pageFromRawData = $pageFromRawData;
        $this->seoMetadataManager = $seoMetadataManager;
        $this->userPreferenceValues = $userPreferenceManager->dataOf('knowledge-graph') ?? [];
    }

    /**
     * User preference: On/Off index website on Google
     *
     * @return bool
     */
    public function indexOnGoogle(): bool
    {
        $data = $this->userPreferenceManager->dataOf(SearchEngineController::USER_PREFERENCE_DATA_KEY);

        if ((!isset($data['robots_index']))) {
            return false;
        }

        return (bool)$data['robots_index'];
    }

    /**
     * Get config
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param Page $page
     *
     * @return string|null
     * @throws Exception
     */
    public function getGraph(Page $page): ?string
    {
        if (false === $this->indexOnGoogle()) {
            return null;
        }

        // init schema context
        $this->context = $this->context ?? $this->initSchemaContext($page);

        return $this->renderer->partial(
            'KnowledgeGraph/graph.html.twig',
            [
                'schemaTag' => $this->getSchemaTag(),
            ]
        );
    }

    /**
     * Get Meta Google site verification
     *
     * @return string|null
     * @throws \BackBee\Renderer\Exception\RendererException
     */
    public function getMetaGoogleSiteVerification(): ?string
    {
        if (false === $this->indexOnGoogle() ||
            empty(($gaData = $this->userPreferenceManager->dataOf('gsc-analytics')))) {
            return null;
        }

        return $this->renderer->partial(
            'KnowledgeGraph/metaGoogleSiteVerification.html.twig',
            [
                'gsc_content' => $gaData['content'] ?? '',
            ]
        );
    }

    /**
     * Get seo metadata.
     *
     * @param Page $page
     *
     * @return string
     */
    public function getSeoMetadata(Page $page): string
    {
        $this->context = $this->context ?? $this->initSchemaContext($page);

        return $this->seoMetadataManager->getRenderSeoMetadata($page, $this->context);
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getSchemaTag(): array
    {
        return [
            'context' => 'https://schema.org',
            'graph' => $this->getPieces(),
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getPieces(): array
    {
        $organization = new SchemaOrganization($this->context, $this->userPreferenceValues);
        $website = new SchemaWebSite($this->context, $this->userPreferenceValues);
        $webpage = new SchemaWebPage($this->context);

        $pieces = [
            $organization->generate(),
            $website->generate(),
            $webpage->generate(),
        ];

        return $this->getExtraMappingTypes($pieces);
    }

    /**
     * @param array $pieces
     *
     * @return array
     */
    protected function getExtraMappingTypes(array $pieces): array
    {
        if (false === is_array($this->config['mapping_schema_types'])) {
            return $pieces;
        }

        $data = $this->context->getData();
        $pageType = $data['type'];

        try {
            foreach ($this->config['mapping_schema_types'] as $key => $mapping) {
                if (true === in_array($pageType, $mapping, true)) {
                    $schema = new ReflectionClass('BackBee\KnowledgeGraph\Schema\Schema' . $key);
                    $instance = $schema->newInstance($this->context);
                    $pieces[] = $instance->generate();
                }
            }
        } catch (ReflectionException $e) {
            $this->app->getLogging()->error('ReflectionException: ' . $e->getMessage());
        }

        return $pieces;
    }

    /**
     * @param Page $page
     *
     * @return SchemaContext
     */
    public function initSchemaContext(Page $page): SchemaContext
    {
        $esData = $this->pageFromRawData->getData($page);

        return new SchemaContext($this->app, $esData, $this->config['graph']);
    }
}
