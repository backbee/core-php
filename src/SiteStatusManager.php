<?php

namespace BackBeeCloud;

use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SiteStatusManager
{
    const REGISTRY_SCOPE = 'GLOBAL';
    const REGISTRY_TYPE = 'site_status';
    const REGISTRY_KEY = 'lock_progression';

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function lock()
    {
        if (null === $this->getLock()) {
            $lock = new Registry();
            $lock->setScope(self::REGISTRY_SCOPE);
            $lock->setType(self::REGISTRY_TYPE);
            $lock->setKey(self::REGISTRY_KEY);
            $lock->setValue(0);

            $this->entityManager->persist($lock);
            $this->entityManager->flush($lock);
        }
    }

    public function unlock()
    {
        if (null !== $lock = $this->getLock()) {
            $this->entityManager->remove($lock);
            $this->entityManager->flush($lock);
        }
    }

    public function getLockProgress()
    {
        if (null === $lock = $this->getLock()) {
            throw new \LogicException('Cannot find any work in progress.');
        }

        return (int) $lock->getValue();
    }

    public function updateLockProgressPercent($percent)
    {
        if (null === $lock = $this->getLock()) {
            throw new \LogicException('Cannot find any work in progress.');
        }

        $lock->setValue((int) $percent);
        $this->entityManager->flush($lock);
    }

    private function getLock()
    {
        return $this->entityManager->getRepository(Registry::class)->findOneBy([
            'scope' => self::REGISTRY_SCOPE,
            'type'  => self::REGISTRY_TYPE,
            'key'   => self::REGISTRY_KEY,
        ]);
    }
}
