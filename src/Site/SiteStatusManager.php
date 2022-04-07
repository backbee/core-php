<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBee\Site;

use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;

/**
 * Class SiteStatusManager
 *
 * @package BackBee\Site
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SiteStatusManager
{
    public const REGISTRY_SCOPE = 'GLOBAL';
    public const REGISTRY_TYPE = 'site_status';
    public const REGISTRY_KEY = 'lock_progression';

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * SiteStatusManager constructor.
     *
     * @param EntityManager   $entityManager
     * @param LoggerInterface $logger
     */
    public function __construct(EntityManager $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Lock site.
     */
    public function lock(): void
    {
        try {
            if (null === $this->getLock()) {
                $lock = new Registry();
                $lock->setScope(self::REGISTRY_SCOPE);
                $lock->setType(self::REGISTRY_TYPE);
                $lock->setKey(self::REGISTRY_KEY);
                $lock->setValue(0);

                $this->entityManager->persist($lock);
                $this->entityManager->flush($lock);
            }
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Unlock site.
     */
    public function unlock(): void
    {
        try {
            if (null !== $lock = $this->getLock()) {
                $this->entityManager->remove($lock);
                $this->entityManager->flush($lock);
            }
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Get lock progress.
     *
     * @return int
     */
    public function getLockProgress(): int
    {
        if (null === $lock = $this->getLock()) {
            throw new LogicException('Cannot find any work in progress.');
        }

        return (int)$lock->getValue();
    }

    /**
     * Update lock progress percent.
     *
     * @param $percent
     */
    public function updateLockProgressPercent($percent): void
    {
        if (null === $lock = $this->getLock()) {
            throw new LogicException('Cannot find any work in progress.');
        }

        try {
            $lock->setValue((int)$percent);
            $this->entityManager->flush($lock);
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Get lock.
     *
     * @return Registry|object|null
     */
    private function getLock()
    {
        return $this->entityManager->getRepository(Registry::class)->findOneBy(
            [
                'scope' => self::REGISTRY_SCOPE,
                'type' => self::REGISTRY_TYPE,
                'key' => self::REGISTRY_KEY,
            ]
        );
    }
}
