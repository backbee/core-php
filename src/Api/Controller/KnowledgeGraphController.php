<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBee\Config\Config;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class KnowledgeGraphController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class KnowledgeGraphController extends AbstractController
{
    /**
     * @var Config
     */
    private $config;

    /**
     * KnowledgeGraphController constructor.
     *
     * @param BBApplication $app
     * @param Config        $config
     */
    public function __construct(BBApplication $app, Config $config)
    {
        $this->config = $config;
        parent::__construct($app);
    }

    /**
     * Get knowledge graph parameters;
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function getParameters(): JsonResponse
    {
        $this->assertIsAuthenticated();

        $configuration = $this->config->getSection('knowledge_graph');

        return new JsonResponse(
            [
                'graph' => [
                    'organization' => $configuration['graph']['name'] ?? 'n/a',
                    'organization_social_profiles' => $configuration['graph']['social_profiles'] ?? 'n/a',
                    'website_name' => $configuration['graph']['website_name'] ?? 'n/a',
                    'website_description' => $configuration['graph']['website_description'] ?? 'n/a',
                    'website_logo' => $configuration['graph']['logo'] ?? 'n/a',
                    'website_image' => $configuration['graph']['image'] ?? 'n/a',
                    'website_search' => $configuration['graph']['website_search'] ?? 'n/a',
                    'twitter_card' => $configuration['graph']['twitter_card'] ?? 'n/a',
                ],
                'mapping_schema_types' => $configuration['mapping_schema_types'] ?? ['n/a' => ['n/a']]
            ]
        );
    }
}
