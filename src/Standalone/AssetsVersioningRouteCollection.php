<?php

namespace BackBeePlanet\Standalone;

use BackBee\ApplicationInterface;
use BackBee\Routing\RouteCollection;
use BackBee\Site\Site;
use BackBeePlanet\GlobalSettings;
use Psr\Log\LoggerInterface;

/**
 * Class AssetsVersioningRouteCollection
 *
 * @package BackBeePlanet\Standalone
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class AssetsVersioningRouteCollection extends RouteCollection
{
    /**
     * @var array
     */
    protected $versionedAssetsMapping = [];

    /**
     * AssetsVersioningRouteCollection constructor.
     *
     * @param ApplicationInterface|null $app
     * @param LoggerInterface|null      $logger
     */
    public function __construct(ApplicationInterface $app = null, LoggerInterface $logger = null)
    {
        parent::__construct($app, $logger);

        $globalSettings = new GlobalSettings();
        if (!$globalSettings->isDevMode()) {
            $this->versionedAssetsMapping = $globalSettings->assets_versioning()['versioned_files_mapping'] ?? [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUri($pathinfo = '', $extension = null, Site $site = null, $urlType = null): string
    {
        if (false !== $this->versionedAssetsMapping && isset($this->versionedAssetsMapping[$pathinfo])) {
            $pathinfo = $this->versionedAssetsMapping[$pathinfo];
        }

        return parent::getUri($pathinfo, $extension, $site, $urlType);
    }
}
