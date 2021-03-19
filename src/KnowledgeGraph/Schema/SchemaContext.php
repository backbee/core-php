<?php

namespace BackBee\KnowledgeGraph\Schema;

use BackBee\BBApplication;

/**
 * Class SchemaContext
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaContext
{
    /**
     * @var BBApplication
     */
    private $app;

    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $config;

    /**
     * SchemaContext constructor.
     *
     * @param BBApplication $app
     * @param array         $data
     * @param array         $config
     */
    public function __construct(BBApplication $app, array $data, array $config)
    {
        $this->app = $app;
        $this->data = $data;
        $this->config = $config;
    }

    /**
     * @return BBApplication
     */
    public function getApplication(): BBApplication
    {
        return $this->app;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
