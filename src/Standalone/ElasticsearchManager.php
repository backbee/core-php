<?php

namespace BackBeePlanet\Standalone;

use BackBeeCloud\Elasticsearch\ElasticsearchManager as BaseElasticSearchManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ElasticsearchManager extends BaseElasticSearchManager
{
    public const INDEX_BASE_NAME = 'backbee_standalone_';

    /**
     * {@inheritdoc}
     */
    public function getIndexName(): string
    {
        return self::INDEX_BASE_NAME . $this->getSiteName();
    }
}
