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

use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeManager;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\Security\Group;
use BackBee\Security\User;
use BackBee\Site\Site;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class GroupTypeManager
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var GroupTypeRightManager
     */
    protected $groupTypeRightManager;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $groupRepository;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $groupTypeRepository;

    /**
     * @var TypeManager
     */
    protected $pageTypeManager;


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

    public function getAllGroupTypes()
    {
        return $this->groupTypeRepository->findAll();
    }

    public function getById($id)
    {
        return $this->groupTypeRepository->findOneBy(['id' => $id]);
    }

    public function findByGroups(array $groups)
    {
        return $this->groupTypeRepository->findBy(['group' => $groups]);
    }

    public function getByGroup(Group $group)
    {
        return $this->groupTypeRepository->findOneBy(['group' => $group]);
    }

    public function create(
        $name,
        $description,
        $isOpen = true,
        $readOnly = false,
        array $featuresSubject = [],
        array $pageRightsData = [],
        array $users = []
    ) {
        $this->entityManager->beginTransaction();

        $groupType = null;

        $group = $this->createGroupEntity($name, $description);
        foreach ($users as $user) {
            if (!($user instanceof User)) {
                throw new \RuntimeException(
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
                (bool) $isOpen,
                (bool) $readOnly,
                $group
            );

            if (UserRightConstants::SUPER_ADMIN_ID !== $groupType->getId()) {
                $this->updateRights($groupType, $featuresSubject, $pageRightsData);
            }
        } catch (\Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->entityManager->flush();
        $this->entityManager->refresh($groupType);

        $this->entityManager->commit();

        return $groupType;
    }

    public function update(
        GroupType $groupType,
        $name,
        $description,
        array $featuresSubject = [],
        array $pageRightsData = []
    ) {
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
        } catch (\Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->entityManager->flush();
        $this->entityManager->commit();
    }

    public function updateRights(GroupType $groupType, array $featuresSubject, array $pageRightsData)
    {
        $rightsToRemove = new \SplObjectStorage();
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

        $pageTypes = isset($pageRightsData['page_types']) ? $pageRightsData['page_types'] : [];
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
            $contextMask = $contextMask + UserRightConstants::PAGE_TYPE_CONTEXT_MASK;
        }

        if ($this->pageCategoryManager->getCategories()) {
            $categories = isset($pageRightsData['categories']) ? $pageRightsData['categories'] : [];
            if (['all'] !== $categories) {
                $categoriesData = [];
                foreach ($pageRightsData['categories'] as $category) {
                    if ($this->pageCategoryManager->hasCategory($category)) {
                        $categoriesData = $category;
                    }
                }

                $contextData['categories'] = $categories;
                $contextMask = $contextMask + UserRightConstants::CATEGORY_CONTEXT_MASK;
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

    public function delete(GroupType $groupType)
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

    public function addUserToGroupType(GroupType $groupType, User $user)
    {
        $groupType->addUser($user);
        $this->entityManager->flush();
    }

    public function removeUserFromGroupType(GroupType $groupType, User $user)
    {
        $groupType->removeUser($user);
        $this->entityManager->flush();
    }

    public function searchByTerm($term)
    {
        $qb = $this->groupRepository->createQueryBuilder('g');

        $groups = $qb
            ->where(
                $qb->expr()->like('g._name', ':name')
            )
            ->setParameter('name', '%' . $term . '%')
            ->getQuery()
            ->getResult()
        ;

        return $this->groupTypeRepository->findBy(['group' => $groups]);
    }

    /**
     * @throws \Exception if group name is already used
     */
    protected function createGroupEntity($name, $description)
    {
        $this->assertNameIsNotUsed($name);

        $group = new Group();

        $group->setName($name);
        $group->setDescription($description);
        $group->setSite($this->getSite());

        $this->entityManager->persist($group);

        return $group;
    }

    protected function createGroupTypeEntity($isOpen, $readOnly, Group $group)
    {
        $slug = new Slugify();
        $name = $slug->slugify($group->getName(), ['separator' => '_']);

        $groupType = new GroupType($name, $isOpen, $readOnly, $group);

        $this->entityManager->persist($groupType);

        return $groupType;
    }

    protected function assertNameIsNotUsed($name)
    {
        if ($group = $this->groupRepository->findOneBy(['_name' => $name])) {
            throw NameAlreadyUsedException::create($name);
        }
    }

    protected function getSite()
    {
        return $this->entityManager->getRepository(Site::class)->findOneBy([]);
    }
}
