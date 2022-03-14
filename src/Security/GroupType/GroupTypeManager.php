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

namespace BackBeeCloud\Security\GroupType;

use BackBee\Security\Group;
use BackBee\Security\User;
use BackBee\Site\Site;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeManager;
use BackBeeCloud\Security\UserRightConstants;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use SplObjectStorage;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class GroupTypeManager
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var GroupTypeRightManager
     */
    private $groupTypeRightManager;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $groupRepository;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $groupTypeRepository;

    /**
     * @var TypeManager
     */
    private $pageTypeManager;

    /**
     * @var \BackBeeCloud\PageCategory\PageCategoryManager
     */
    private $pageCategoryManager;

    /**
     * Constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface                   $entityManager
     * @param \BackBeeCloud\Security\GroupType\GroupTypeRightManager $groupTypeRightManager
     * @param \BackBeeCloud\PageType\TypeManager                     $typeManager
     * @param \BackBeeCloud\PageCategory\PageCategoryManager         $pageCategoryManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GroupTypeRightManager $groupTypeRightManager,
        TypeManager $typeManager,
        PageCategoryManager $pageCategoryManager
    ) {
        $this->entityManager = $entityManager;
        $this->groupTypeRightManager = $groupTypeRightManager;
        $this->pageTypeManager = $typeManager;
        $this->pageCategoryManager = $pageCategoryManager;
        $this->groupRepository = $entityManager->getRepository(Group::class);
        $this->groupTypeRepository = $entityManager->getRepository(GroupType::class);
    }

    /**
     * Get all group types.
     *
     * @return array|\BackBeeCloud\Security\GroupType\GroupType[]
     */
    public function getAllGroupTypes(): array
    {
        return $this->groupTypeRepository->findAll();
    }

    /**
     * Get group type by id.
     *
     * @param $id
     *
     * @return null|\BackBeeCloud\Security\GroupType\GroupType
     */
    public function getById($id): ?GroupType
    {
        return $this->groupTypeRepository->findOneBy(['id' => $id]);
    }

    /**
     * Get group type by groups.
     *
     * @param array $groups
     *
     * @return array|\BackBeeCloud\Security\GroupType\GroupType[]
     */
    public function findByGroups(array $groups): array
    {
        return $this->groupTypeRepository->findBy(['group' => $groups]);
    }

    /**
     * Get group type by group.
     *
     * @param \BackBee\Security\Group $group
     *
     * @return null|\BackBeeCloud\Security\GroupType\GroupType
     */
    public function getByGroup(Group $group): ?GroupType
    {
        return $this->groupTypeRepository->findOneBy(['group' => $group]);
    }

    /**
     * Create group type.
     *
     * @param       $name
     * @param       $description
     * @param bool  $isOpen
     * @param bool  $readOnly
     * @param array $featuresSubject
     * @param array $pageRightsData
     * @param array $users
     *
     * @return \BackBeeCloud\Security\GroupType\GroupType
     * @throws \Exception
     */
    public function create(
        $name,
        $description,
        bool $isOpen = true,
        bool $readOnly = false,
        array $featuresSubject = [],
        array $pageRightsData = [],
        array $users = []
    ): GroupType {
        $this->entityManager->beginTransaction();

        $group = $this->createGroupEntity($name, $description);

        foreach ($users as $user) {
            if (!($user instanceof User)) {
                throw new RuntimeException(
                    sprintf(
                        'Only user that is instance of %s can be added to group type.',
                        User::class
                    )
                );
            }

            $group->addUser($user);
        }

        try {
            $groupType = $this->createGroupTypeEntity(
                $isOpen,
                $readOnly,
                $group
            );

            if (UserRightConstants::SUPER_ADMIN_ID !== $groupType->getId()) {
                $this->updateRights($groupType, $featuresSubject, $pageRightsData);
            }
        } catch (Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->entityManager->flush();
        $this->entityManager->refresh($groupType);

        $this->entityManager->commit();

        return $groupType;
    }

    /**
     * Update.
     *
     * @param \BackBeeCloud\Security\GroupType\GroupType $groupType
     * @param                                            $name
     * @param                                            $description
     * @param array                                      $featuresSubject
     * @param array                                      $pageRightsData
     *
     * @throws \Exception
     */
    public function update(
        GroupType $groupType,
        $name,
        $description,
        array $featuresSubject = [],
        array $pageRightsData = []
    ): void {
        $this->entityManager->beginTransaction();

        try {
            if ($groupType->getName() !== $name) {
                if (strtolower($groupType->getName()) !== strtolower($name)) {
                    $this->assertNameIsNotUsed($name);
                }

                $groupType->setName($name);
            }

            $groupType->setDescription($description);
            $this->updateRights($groupType, $featuresSubject, $pageRightsData);
        } catch (Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->entityManager->flush();
        $this->entityManager->commit();
    }

    /**
     * Update rights.
     *
     * @param \BackBeeCloud\Security\GroupType\GroupType $groupType
     * @param array                                      $featuresSubject
     * @param array                                      $pageRightsData
     */
    public function updateRights(GroupType $groupType, array $featuresSubject, array $pageRightsData): void
    {
        $rightsToRemove = new SplObjectStorage();
        foreach ($this->groupTypeRightManager->findByGroupType($groupType) as $right) {
            $rightsToRemove->attach($right);
        }

        $createRightIfNotExist = function (
            GroupType $groupType,
            $subject,
            $attribute,
            $contextMask = UserRightConstants::NO_CONTEXT_MASK,
            array $contextData = []
        ) use ($rightsToRemove) {
            $right = $this->groupTypeRightManager->findOneBy(
                $groupType,
                $subject,
                $attribute,
                $contextMask,
                $contextData
            );
            if ($right) {
                $rightsToRemove->detach($right);

                return;
            }

            $this->groupTypeRightManager->create($groupType, $subject, $attribute, $contextMask, $contextData);
        };
        $createRightIfNotExist->bindTo($this);

        // handle features subjects
        foreach ($featuresSubject as $subject) {
            $createRightIfNotExist(
                $groupType,
                $subject,
                UserRightConstants::MANAGE_ATTRIBUTE,
                UserRightConstants::NO_CONTEXT_MASK
            );
        }

        // handle pages rights
        $pageTypes = $pageRightsData['page_types'] ?? [];
        // if no page type selected, there is no rights on pages to apply
        $contextMask = UserRightConstants::NO_CONTEXT_MASK;
        $contextData = [];

        if (['all'] !== $pageTypes) {
            $pageTypesData = [];
            foreach ($pageTypes as $pageTypeId) {
                if ($this->pageTypeManager->find($pageTypeId)) {
                    $pageTypesData[] = $pageTypeId;
                }
            }

            $contextData['page_types'] = $pageTypesData;
            $contextMask += UserRightConstants::PAGE_TYPE_CONTEXT_MASK;
        }

        if ($this->pageCategoryManager->getCategories()) {
            $categories = $pageRightsData['categories'] ?? [];
            if (['all'] !== $categories) {
                $contextData['categories'] = $categories;
                $contextMask += UserRightConstants::CATEGORY_CONTEXT_MASK;
            }
        }

        foreach ($pageRightsData['offline_page'] as $attribute) {
            $createRightIfNotExist(
                $groupType,
                UserRightConstants::OFFLINE_PAGE,
                $attribute,
                $contextMask,
                $contextData
            );
        }

        foreach ($pageRightsData['online_page'] as $attribute) {
            $createRightIfNotExist(
                $groupType,
                UserRightConstants::ONLINE_PAGE,
                $attribute,
                $contextMask,
                $contextData
            );
        }

        foreach ($rightsToRemove as $right) {
            $this->entityManager->remove($right);
        }
    }

    /**
     * Delete group type.
     *
     * @param \BackBeeCloud\Security\GroupType\GroupType $groupType
     *
     * @return void
     */
    public function delete(GroupType $groupType): void
    {
        if ($groupType->getUsers()) {
            throw CannotDeleteGroupTypeWithUsersException::create();
        }

        if ($groupType->isReadOnly()) {
            throw CannotDeleteReadOnlyException::create();
        }

        $this->entityManager->remove($groupType);
        $this->entityManager->flush();
    }

    /**
     * Add user to group type.
     *
     * @param \BackBeeCloud\Security\GroupType\GroupType $groupType
     * @param \BackBee\Security\User                     $user
     *
     * @return void
     */
    public function addUserToGroupType(GroupType $groupType, User $user): void
    {
        $groupType->addUser($user);
        $this->entityManager->flush();
    }

    /**
     * Remove user from group type.
     *
     * @param \BackBeeCloud\Security\GroupType\GroupType $groupType
     * @param \BackBee\Security\User                     $user
     *
     * @return void
     */
    public function removeUserFromGroupType(GroupType $groupType, User $user): void
    {
        $groupType->removeUser($user);
        $this->entityManager->flush();
    }

    /**
     * Search group type by term.
     *
     * @param $term
     *
     * @return array
     */
    public function searchByTerm($term): array
    {
        $qb = $this->groupRepository->createQueryBuilder('g');

        $groups = $qb
            ->where(
                $qb->expr()->like('g._name', ':name')
            )
            ->setParameter('name', '%' . $term . '%')
            ->getQuery()
            ->getResult();

        return $this->groupTypeRepository->findBy(['group' => $groups]);
    }

    /**
     * Create group entity.
     *
     * @param $name
     * @param $description
     *
     * @return \BackBee\Security\Group
     */
    protected function createGroupEntity($name, $description): Group
    {
        $this->assertNameIsNotUsed($name);

        $group = new Group();

        $group->setName($name);
        $group->setDescription($description);
        $group->setSite($this->getSite());

        $this->entityManager->persist($group);

        return $group;
    }

    /**
     * Create group type entity.
     *
     * @param                         $isOpen
     * @param                         $readOnly
     * @param \BackBee\Security\Group $group
     *
     * @return \BackBeeCloud\Security\GroupType\GroupType
     */
    protected function createGroupTypeEntity($isOpen, $readOnly, Group $group): GroupType
    {
        $slug = new Slugify();
        $name = $slug->slugify($group->getName(), ['separator' => '_']);

        $groupType = new GroupType($name, $isOpen, $readOnly, $group);

        $this->entityManager->persist($groupType);

        return $groupType;
    }

    /**
     * Assert name is not used.
     *
     * @param $name
     *
     * @return void
     */
    protected function assertNameIsNotUsed($name): void
    {
        if ($this->groupRepository->findOneBy(['_name' => $name])) {
            throw NameAlreadyUsedException::create($name);
        }
    }

    /**
     * Get current site.
     *
     * @return null|Site
     */
    protected function getSite(): ?Site
    {
        return $this->entityManager->getRepository(Site::class)->findOneBy([]);
    }
}
