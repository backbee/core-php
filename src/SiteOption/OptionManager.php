<?php

namespace BackBeeCloud\SiteOption;

use BackBee\Bundle\Registry;
use BackBee\Config\Config;
use Doctrine\ORM\EntityManager;
use function in_array;

/**
 * Class OptionManager
 *
 * @package BackBeeCloud\SiteOption
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class OptionManager
{
    public const REGISTRY_SCOPE = 'GLOBAL';
    public const REGISTRY_TYPE = 'site_options';

    /**
     * @var array|null
     */
    protected $options;

    /**
     * @var Registry\Repository
     */
    protected $repository;

    /**
     * OptionManager constructor.
     *
     * @param EntityManager $entityManager
     * @param Config        $config
     */
    public function __construct(EntityManager $entityManager, Config $config)
    {
        $this->options = $config->getSection('site_options');
        $this->repository = $entityManager->getRepository(Registry::class);
    }

    /**
     * Is active option.
     *
     * @param $name
     *
     * @return bool
     */
    public function isActiveOption($name): bool
    {
        return in_array($name, $this->options, true)
            ? null !== $this->repository->findOneBy(
                [
                    'scope' => self::REGISTRY_SCOPE,
                    'type' => self::REGISTRY_TYPE,
                    'key' => $name,
                ]
            )
            : false;
    }
}
