<?php

namespace BackBee\KnowledgeGraph\Schema;

/**
 * Interface SchemaInterface
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
interface SchemaInterface
{
    /**
     *
     * @return array|bool
     */
    public function generate();
}
