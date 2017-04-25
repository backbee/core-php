<?php

namespace BackBeeCloud\SiteOption;

use BackBeePlanet\GlobalSettings;
use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class OptionManager
{
    const REGISTRY_SCOPE = 'GLOBAL';
    const REGISTRY_TYPE = 'site_options';

    protected $options;
    protected $repository;

    public function __construct(EntityManager $entyMgr)
    {
        $this->options = (array) (new GlobalSettings())->siteOptions();
        $this->repository = $entyMgr->getRepository(Registry::class);
    }

    public function isActiveOption($name)
    {
        return in_array($name, $this->options)
            ? null !== $this->repository->findOneBy([
                'scope' => self::REGISTRY_SCOPE,
                'type'  => self::REGISTRY_TYPE,
                'key'   => $name,
            ])
            : false
        ;
    }
}
