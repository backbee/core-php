<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

namespace BackBeeCloud\Security;

use BackBee\Bundle\Registry;
use BackBee\Config\Config;
use BackBee\Security\Group;
use BackBeeCloud\Security\GroupType\GroupType;
use BackBeeCloud\Security\GroupType\GroupTypeManager;
use BackBeeCloud\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class UserRightInstaller
 *
 * @package BackBeeCloud\Security
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightInstaller
{
    public const REGISTRY_SCOPE = 'GLOBAL';
    public const REGISTRY_TYPE = 'USER_RIGHT_FEATURE';
    public const REGISTRY_KEY = 'is_installed';

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var GroupTypeManager
     */
    private $groupTypeManager;

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    /**
     * UserRightInstaller constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param GroupTypeManager       $groupTypeManager
     * @param UserManager            $userManager
     * @param LoggerInterface        $logger
     * @param Config                 $config
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GroupTypeManager $groupTypeManager,
        UserManager $userManager,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->entityManager = $entityManager;
        $this->groupTypeManager = $groupTypeManager;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Install.
     *
     * @param array $rawGroupTypes
     * @param       $defaultGroupTypeId
     */
    public function install(array $rawGroupTypes, $defaultGroupTypeId): void
    {
        if ($this->isInstalled()) {
            return;
        }

        $this->entityManager->beginTransaction();

        // removing all groups...
        $this->removeGroups();

        // creating group types and assigning users...
        $this->buildGroupTypes($rawGroupTypes, $defaultGroupTypeId);

        $this->cleanAcl();
        $this->markAsInstalled();

        $this->entityManager->commit();
    }

    /**
     * Check if installed.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        if (null === $registry = $this->getRegistryEntity()) {
            return false;
        }

        return (bool)$registry->getValue();
    }

    /**
     * Sync group types.
     *
     * @param $rawGroupTypes
     */
    public function syncGroupTypes($rawGroupTypes): void
    {
        $this->entityManager->beginTransaction();

        try {
            foreach ($rawGroupTypes as $id => $rawData) {
                $groupType = $this->groupTypeManager->getById($id);
                if (null === $groupType) {
                    $this->createGroupTypeFromRawData($id, $rawData);

                    continue;
                }

                $this->updateGroupTypeName(
                    $groupType,
                    $rawData['name'] ?? null
                );

                if (isset($rawData['features_rights'], $rawData['pages_rights'])) {
                    $this->groupTypeManager->updateRights(
                        $groupType,
                        $rawData['features_rights'],
                        $rawData['pages_rights']
                    );
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
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
     * Remove groups.
     */
    private function removeGroups(): void
    {
        foreach ($this->entityManager->getRepository(Group::class)->findAll() as $group) {
            $this->entityManager->remove($group);
        }

        $this->entityManager->flush();
    }

    /**
     * Build group types.
     *
     * @param array $rawGroupTypes
     * @param       $defaultGroupTypeId
     */
    private function buildGroupTypes(array $rawGroupTypes, $defaultGroupTypeId): void
    {
        // gathering users...
        $users = $this->userManager->getAll();
        $superAdminUsers = array_filter($users, [$this->userManager, 'isMainUser']);
        $regularUsers = array_diff($users, $superAdminUsers);

        // creating group types...
        $superAdminGroupType = null;
        $defaultGroupType = null;
        foreach ($rawGroupTypes as $id => $data) {
            $users = [];
            if ($defaultGroupTypeId === $id) {
                $users = array_merge($users, $regularUsers);
            }

            $isOpened = (bool)$data['is_opened'];
            $readOnly = (bool)$data['read_only'];
            if (!$isOpened && $readOnly) {
                $users = array_merge($users, $superAdminUsers);
            }

            $this->createGroupTypeFromRawData($id, $data, $users);
        }

        $this->entityManager->flush();
    }

    /**
     * Clean acl.
     */
    private function cleanAcl(): void
    {
        try {
            $this->entityManager->getConnection()->executeUpdate('SET foreign_key_checks = 0;');
            $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_classes;');
            $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_entries;');
            $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_object_identities;');
            $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_object_identity_ancestors;');
            $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_security_identities;');
            $this->entityManager->getConnection()->executeUpdate('SET foreign_key_checks = 1;');
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
     * Mark as installed.
     */
    private function markAsInstalled(): void
    {
        $registry = new Registry();
        $registry->setScope(self::REGISTRY_SCOPE);
        $registry->setType(self::REGISTRY_TYPE);
        $registry->setKey(self::REGISTRY_KEY);
        $registry->setValue(1);
        $this->entityManager->persist($registry);
        $this->entityManager->flush();
    }

    /**
     * Get registry entity.
     *
     * @return Registry|null
     */
    private function getRegistryEntity(): ?Registry
    {
        return $this->entityManager->getRepository(Registry::class)->findOneBy(
            [
                'scope' => self::REGISTRY_SCOPE,
                'type' => self::REGISTRY_TYPE,
                'key' => self::REGISTRY_KEY,
            ]
        );
    }

    /**
     * Create group type from raw data.
     *
     * @param       $id
     * @param array $data
     * @param array $users
     *
     * @return GroupType
     */
    private function createGroupTypeFromRawData($id, array $data, array $users = []): GroupType
    {
        $groupType = null;

        try {
            $groupType = $this->groupTypeManager->create(
                $id,
                $id . '_description',
                $data['is_opened'],
                $data['read_only'],
                $data['features_rights'] ?? [],
                $data['pages_rights'] ?? [],
                $users
            );

            $this->updateGroupTypeName(
                $groupType,
                $data['name'] ?? null
            );
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

        return $groupType;
    }

    /**
     * Update group type name.
     *
     * @param GroupType $groupType
     * @param null      $newName
     */
    private function updateGroupTypeName(GroupType $groupType, $newName = null): void
    {
        if ($newName && $newName !== $groupType->getName()) {
            $group = $this->getGroupByGroupType($groupType);
            $group->setName($newName);
        }
    }

    /**
     * Get group by group type.
     *
     * @param GroupType $groupType
     *
     * @return Group|null
     */
    private function getGroupByGroupType(GroupType $groupType): ?Group
    {
        $groupId = 1;
        $qb = $this->entityManager->getRepository(GroupType::class)->createQueryBuilder('gt');

        try {
            $groupId = (int)$qb
                ->select('g._id')
                ->join('gt.group', 'g')
                ->where(
                    $qb->expr()->eq('gt.id', ':id')
                )
                ->setParameter('id', $groupType->getId())
                ->getQuery()
                ->getSingleScalarResult();
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

        return $this->entityManager->find(Group::class, $groupId);
    }
}
