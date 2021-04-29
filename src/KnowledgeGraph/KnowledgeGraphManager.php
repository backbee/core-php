<?php

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
     * KnowledgeGraphManager constructor.
     *
     * @param BBApplication         $app
     * @param UserPreferenceManager $userPreferenceManager
     * @param Config                $config
     * @param PageFromRawData       $pageFromRawData
     * @param SeoMetadataManager    $seoMetadataManager
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
    }

    /**
     * User preference: On/Off index website on Google
     *
     * @return bool
     */
    public function indexOnGoogle(): bool
    {
        $data = $this->userPreferenceManager->dataOf(SearchEngineController::USER_PREFERENCE_DATA_KEY);
        if ((null === $data) || (!isset($data['robots_index']))) {
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
     * @return string|null
     * @throws RendererException
     */
    public function getMetaGoogleSiteVerification(): ?string
    {
        if (false === $this->indexOnGoogle()) {
            return null;
        }

        return $this->renderer->partial(
            'KnowledgeGraph/metaGoogleSiteVerification.html.twig',
            [
                'googleSiteVerification' => $this->config['google_site_verification'],
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
        $organization = new SchemaOrganization($this->context);
        $website = new SchemaWebSite($this->context);
        $webpage = new SchemaWebPage($this->context);

        $pieces = [
            $organization->generate(),
            $website->generate(),
            $webpage->generate(),
        ];

        $pieces = $this->getExtraMappingTypes($pieces);

        return $pieces;
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
