<?php

namespace BackBeeCloud;

use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UpdateTaskScheduler
{
    const VERSION = '3.0.4';

    const VOID_MASK = 0;
    const ELASTICSEARCH_MASK = 1;

    const REGISTRY_SCOPE = 'UPDATE_SCHEDULER';
    const REGISTRY_KEY = 'current_version';

    /**
     * @var EntityManager
     */
    protected $entyMgr;

    public function __construct(EntityManager $entyMgr)
    {
        $this->entyMgr = $entyMgr;
    }

    public function isUpdateRequired($name)
    {
        $constant = sprintf('self::%s_MASK', strtoupper($name));
        if (!defined($constant)) {
            return false;
        }

        if ($this->isUpToDate()) {
            return false;
        }

        $lastUpdate = $this->getUpdates()[self::VERSION];

        return 0 !== (constant($constant) & $lastUpdate['update_mask']);
    }

    public function updateVersion()
    {
        if (null === $registry = $this->getRegistryEntity()) {
            $registry = new Registry();
            $registry->setScope(self::REGISTRY_SCOPE);
            $registry->setKey(self::REGISTRY_KEY);
            $this->entyMgr->persist($registry);
        }

        $registry->setValue(self::VERSION);
        $this->entyMgr->flush($registry);
    }

    public function isUpToDate()
    {
        $registry = $this->entyMgr->getRepository(Registry::class)->findOneBy([
            'scope' => 'UPDATE_SCHEDULER',
            'key'   => 'current_version',
        ]);

        return $registry ? self::VERSION === $registry->getValue() : false;
    }

    protected function getUpdates()
    {
        return [
            '1.3.12' => [
                'version'     => '1.3.12',
                'update_mask' => self::ELASTICSEARCH_MASK,
            ],
            '1.3.13' => [
                'version'     => '1.3.13',
                'update_mask' => self::ELASTICSEARCH_MASK,
            ],
            '1.4.1' => [
                'version'     => '1.4.1',
                'update_mask' => self::ELASTICSEARCH_MASK,
            ],
            '1.4.7' => [
                'version'     => '1.4.7',
                'update_mask' => self::ELASTICSEARCH_MASK,
            ],
            '1.4.18' => [
                'version'     => '1.4.18',
                'update_mask' => self::ELASTICSEARCH_MASK,
            ],
            '2.0.10' => [
                'version'     => '2.0.10',
                'update_mask' => self::ELASTICSEARCH_MASK,
            ],
            '3.0.4' => [
                'version'     => '3.0.4',
                'update_mask' => self::ELASTICSEARCH_MASK,
            ],
        ];
    }

    protected function getRegistryEntity()
    {
        return $this->entyMgr->getRepository(Registry::class)->findOneBy([
            'scope' => self::REGISTRY_SCOPE,
            'key'   => self::REGISTRY_KEY,
        ]);
    }
}
