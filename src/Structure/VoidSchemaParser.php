<?php

namespace BackBeeCloud\Structure;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class VoidSchemaParser implements SchemaParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function getSchema($name)
    {
        return [];
    }
}
