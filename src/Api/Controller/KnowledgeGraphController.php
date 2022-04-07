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

namespace BackBee\Api\Controller;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBeeCloud\Api\Controller\AbstractController;
use BackBeeCloud\UserPreference\UserPreferenceManager;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class KnowledgeGraphController
 *
 * @package BackBee\Api\Controller
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class KnowledgeGraphController extends AbstractController
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var UserPreferenceManager
     */
    private $userPreferenceManager;

    /**
     * KnowledgeGraphController constructor.
     *
     * @param BBApplication $app
     * @param Config $config
     * @param \BackBeeCloud\UserPreference\UserPreferenceManager $userPreferenceManager
     */
    public function __construct(BBApplication $app, Config $config, UserPreferenceManager $userPreferenceManager)
    {
        $this->config = $config;
        $this->userPreferenceManager = $userPreferenceManager;

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
        $values = $this->userPreferenceManager->dataOf('knowledge-graph');

        return new JsonResponse(
            [
                'graph' => [
                    'organization' => $values['organization'] ?? $configuration['graph']['name'] ?? 'n/a',
                    'organization_social_profiles' => json_decode(
                        $values['organization_social_profiles'],
                        false
                    ) ?? $configuration['graph']['social_profiles'] ?? 'n/a',
                    'website_name' => $values['website_name'] ?? $configuration['graph']['website_name'] ?? 'n/a',
                    'website_description' => $values['website_description'] ??
                        $configuration['graph']['website_description'] ?? 'n/a',
                    'website_logo' => $configuration['graph']['logo'] ?? 'n/a',
                    'website_image' => $configuration['graph']['image'] ?? 'n/a',
                ],
            ]
        );
    }
}
