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

namespace BackBeeCloud\Security\GroupType;

use BackBeeCloud\Security\UserRightConstants;
use BackBee\BBApplication;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class GroupTypeRightManager
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $groupTypeRightRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->groupTypeRightRepository = $this->entityManager->getRepository(GroupTypeRight::class);
    }

    public function create(
        GroupType $groupType,
        $subject,
        $attribute,
        $contextMask = UserRightConstants::NO_CONTEXT_MASK,
        array $contextData = []
    ) {
        $groupTypeRight = new GroupTypeRight(
            $groupType,
            $subject,
            $attribute,
            $contextMask,
            $contextData
        );
        $this->entityManager->persist($groupTypeRight);

        return $groupTypeRight;
    }

    public function findOneBy(
        GroupType $groupType,
        $subject,
        $attribute,
        $contextMask = UserRightConstants::NO_CONTEXT_MASK,
        array $contextData = []
    ) {
        $result = $this->entityManager->getConnection()->executeQuery(
            'SELECT id
            FROM group_type_right
            WHERE group_type_id = :group_type_id
            AND subject = :subject
            AND attribute = :attribute
            AND context_mask = :context_mask
            AND context_data = :context_data',
            [
                'group_type_id' => $groupType->getId(),
                'subject' => $subject,
                'attribute' => $attribute,
                'context_mask' => $contextMask,
                'context_data' => json_encode(UserRightConstants::normalizeContextData($contextData)),
            ]
        )->fetch();

        if (false === $result) {
            return null;
        }

        return $this->groupTypeRightRepository->find($result['id']);
    }

    public function findBy(array $criteria, array $orderBy = null)
    {
        return $this->groupTypeRightRepository->findBy($criteria, $orderBy);
    }

    public function findByGroupType(GroupType $groupType)
    {
        return $this->groupTypeRightRepository->findBy(['groupType' => $groupType]);
    }
}
