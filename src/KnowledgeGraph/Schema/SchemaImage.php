<?php

namespace BackBee\KnowledgeGraph\Schema;

/**
 * Class SchemaImage
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaImage
{
    /**
     * @var string
     */
    private $schemaId;

    /**
     * SchemaImage constructor.
     *
     * @param $schemaId
     */
    public function __construct($schemaId)
    {
        $this->schemaId = $schemaId;
    }

    /**
     * Returns the Organization Schema data.
     *
     * @param string $url
     *
     * @return array $data The Organization schema.
     */
    public function generate(string $url): array
    {
        return [
            '@type' => 'ImageObject',
            '@id' => $this->schemaId,
            'url' => $url,
        ];
    }
}
