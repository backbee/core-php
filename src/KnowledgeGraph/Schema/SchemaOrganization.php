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

/**
 * Class SchemaOrganization
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author  Michel Baptista <michel.baptista@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SchemaOrganization implements SchemaInterface
{
    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $userPreferenceValues;

    /**
     * SchemaOrganization constructor.
     *
     * @param SchemaContext $context
     * @param array         $userPreferenceValues
     */
    public function __construct(SchemaContext $context, array $userPreferenceValues)
    {
        $this->config = $context->getConfig();
        $this->renderer = $context->getApplication()->getRenderer();
        $this->userPreferenceValues = $userPreferenceValues;
    }

    /**
     * Returns the Organization Schema data.
     *
     * @return array $data The Organization schema.
     */
    public function generate(): array
    {
        $data = [
            '@type' => 'Organization',
            '@id' => $this->renderer->getUri('/') . SchemaIds::ORGANIZATION_HASH,
            'name' => $this->userPreferenceValues['organization'] ?? $this->config['name'],
            'url' => $this->renderer->getUri('/'),
        ];

        $data = $this->addLogo($data);
        $data = $this->addImage($data);

        return $this->processSocialProfiles($data);
    }

    /**
     * Adds a site's logo.
     *
     * @param array $data The Organization schema.
     *
     * @return array $data The Organization schema.
     */
    private function addLogo(array $data): array
    {
        if (null === $this->config['logo']) {
            return $data;
        }

        $schemaId = $this->renderer->getUri('/') . SchemaIds::ORGANIZATION_LOGO_HASH;
        $schemaImage = new SchemaImage($schemaId);
        $data['logo'] = $schemaImage->generate($this->renderer->getUri($this->config['logo']));

        return $data;
    }

    /**
     * Adds a site's image.
     *
     * @param array $data The Organization schema.
     *
     * @return array $data The Organization schema.
     */
    private function addImage(array $data): array
    {
        if (null === $this->config['image']) {
            return $data;
        }

        $schemaId = $this->renderer->getUri('/') . SchemaIds::ORGANIZATION_IMAGE_HASH;
        $schemaImage = new SchemaImage($schemaId);
        $data['image'] = $schemaImage->generate($this->renderer->getUri($this->config['image']));

        return $data;
    }

    /**
     * Retrieve the social profiles to display in the organization schema.
     *
     * @param array $data
     *
     * @return array $profiles An array of social profiles.
     */
    private function processSocialProfiles(array $data): array
    {
        $socialProfiles = $this->userPreferenceValues['organization_social_profiles'] ??
            $this->config['social_profiles'];

        $data['sameAs'] = is_string($socialProfiles) ? json_decode($socialProfiles, false) : $socialProfiles;

        return $data;
    }
}
