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
