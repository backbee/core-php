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

namespace BackBeeCloud\User;

use BackBee\Security\User;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserManager
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getAll($returnIterator = false)
    {
        $query = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')->getQuery();

        return $returnIterator ? $query->iterate() : $query->getResult();
    }

    public function getById($id)
    {
        return $this->entityManager->find(User::class, $id);
    }

    /**
     * Returns true if provided user is the first user created, else false.
     *
     * @param  User $user
     *
     * @return bool
     */
    public function isMainUser(User $user)
    {
        return 1 === $user->getId();
    }
}
