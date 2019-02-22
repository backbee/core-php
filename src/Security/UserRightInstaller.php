<?php

namespace BackBeeCloud\Security;

use BackBeeCloud\Security\GroupType\GroupType;
use BackBeeCloud\Security\GroupType\GroupTypeManager;
use BackBeeCloud\User\UserManager;
use BackBee\Bundle\Registry;
use BackBee\Security\Group;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightInstaller
{
    const REGISTRY_SCOPE = 'GLOBAL';
    const REGISTRY_TYPE = 'USER_RIGHT_FEATURE';
    const REGISTRY_KEY = 'is_installed';

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

    public function __construct(
        EntityManagerInterface $entityManager,
        GroupTypeManager $groupTypeManager,
        UserManager $userManager
    ) {
        $this->entityManager = $entityManager;
        $this->groupTypeManager = $groupTypeManager;
        $this->userManager = $userManager;
    }

    public function install(array $rawGroupTypes, $defaultGroupTypeId)
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

    public function isInstalled()
    {
        if (null === $registry = $this->getRegistryEntity()) {
            return false;
        }

        return (bool) $registry->getValue();
    }

    public function syncGroupTypes($rawGroupTypes)
    {
        $this->entityManager->beginTransaction();

        foreach ($rawGroupTypes as $id => $rawData) {
            $groupType = $this->groupTypeManager->getById($id);
            if (null === $groupType) {
                $this->createGroupTypeFromRawData($id, $rawData);

                continue;
            }

            $this->updateGroupTypeName(
                $groupType,
                isset($rawData['name']) ? $rawData['name'] : null
            );

            if (isset($rawData['features_rights']) && isset($rawData['pages_rights'])) {
                $this->groupTypeManager->updateRights(
                    $groupType,
                    $rawData['features_rights'],
                    $rawData['pages_rights']
                );
            }
        }

        $this->entityManager->flush();
        $this->entityManager->commit();
    }

    private function removeGroups()
    {
        foreach ($this->entityManager->getRepository(Group::class)->findAll() as $group) {
            $this->entityManager->remove($group);
        }

        $this->entityManager->flush();
    }

    private function buildGroupTypes(array $rawGroupTypes, $defaultGroupTypeId)
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

            $isOpened = (bool) $data['is_opened'];
            $readOnly = (bool) $data['read_only'];
            if (!$isOpened && $readOnly) {
                $users = array_merge($users, $superAdminUsers);
            }

            $this->createGroupTypeFromRawData($id, $data, $users);
        }

        $this->entityManager->flush();
    }

    private function cleanAcl()
    {
        $this->entityManager->getConnection()->executeUpdate('SET foreign_key_checks = 0;');
        $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_classes;');
        $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_entries;');
        $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_object_identities;');
        $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_object_identity_ancestors;');
        $this->entityManager->getConnection()->executeUpdate('TRUNCATE acl_security_identities;');
        $this->entityManager->getConnection()->executeUpdate('SET foreign_key_checks = 1;');
    }

    private function markAsInstalled()
    {
        $registry = new Registry();
        $registry->setScope(self::REGISTRY_SCOPE);
        $registry->setType(self::REGISTRY_TYPE);
        $registry->setKey(self::REGISTRY_KEY);
        $registry->setValue(1);
        $this->entityManager->persist($registry);
        $this->entityManager->flush($registry);
    }

    private function getRegistryEntity()
    {
        return $this->entityManager->getRepository(Registry::class)->findOneBy([
            'scope' => self::REGISTRY_SCOPE,
            'type' => self::REGISTRY_TYPE,
            'key' => self::REGISTRY_KEY,
        ]);
    }

    private function createGroupTypeFromRawData($id, array $data, array $users = [])
    {
        $groupType = $this->groupTypeManager->create(
            $id,
            $id . '_description',
            $data['is_opened'],
            $data['read_only'],
            isset($data['features_rights']) ? $data['features_rights'] : [],
            isset($data['pages_rights']) ? $data['pages_rights'] : [],
            $users
        );

        $this->updateGroupTypeName(
            $groupType,
            isset($data['name']) ? $data['name'] : null
        );

        return $groupType;
    }

    private function updateGroupTypeName(GroupType $groupType, $newName = null)
    {
        if ($newName && $newName !== $groupType->getName()) {
            $group = $this->getGroupByGroupType($groupType);
            $group->setName($newName);
        }
    }

    private function getGroupByGroupType(GroupType $groupType)
    {
        $qb = $this->entityManager->getRepository(GroupType::class)->createQueryBuilder('gt');

        $groupId = (int) $qb
            ->select('g._id')
            ->join('gt.group', 'g')
            ->where(
                $qb->expr()->eq('gt.id', ':id')
            )
            ->setParameter('id', $groupType->getId())
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $this->entityManager->find(Group::class, $groupId);
    }
}
