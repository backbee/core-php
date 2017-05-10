<?php

namespace BackBeeCloud\Structure;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface SchemaParserInterface
{
    /**
     * Returns structure schema associated to the provided name.
     *
     * @param  string $name
     * @return array
     */
    public function getSchema($name);
}
