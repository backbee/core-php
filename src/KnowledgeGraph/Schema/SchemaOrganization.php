<?php

namespace BackBee\KnowledgeGraph\Schema;

use BackBee\Renderer\Renderer;

/**
 * Class SchemaOrganization
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
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
     * SchemaOrganization constructor.
     *
     * @param SchemaContext $context
     */
    public function __construct(SchemaContext $context)
    {
        $this->config = $context->getConfig();
        $this->renderer = $context->getApplication()->getRenderer();
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
            'name' => $this->config['name'],
            'url' => $this->renderer->getUri('/'),
        ];

        $data = $this->addLogo($data);
        $data = $this->addImage($data);
        $data = $this->processSocialProfiles($data);

        return $data;
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
        if (null === $this->config['social_profiles']) {
            return $data;
        }

        $data['sameAs'] = $this->config['social_profiles'];

        return $data;
    }
}
