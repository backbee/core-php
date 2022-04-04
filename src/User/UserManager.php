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
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class UserManager
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @param \Psr\Log\LoggerInterface             $logger
     */
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Get all users.
     *
     * @param bool $returnIterator
     *
     * @return array|\Doctrine\ORM\Internal\Hydration\IterableResult|float|int|string
     */
    public function getAll(bool $returnIterator = false)
    {
        $query = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')->getQuery();

        return $returnIterator ? $query->iterate() : $query->getResult();
    }

    /**
     * Get user by id.
     *
     * @param $id
     *
     * @return null|\BackBee\Security\User|object
     */
    public function getById($id)
    {
        try {
            $user = $this->entityManager->find(User::class, $id);
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

        return $user ?? null;
    }

    /**
     * Returns true if provided user is the first user created, else false.
     *
     * @param User $user
     *
     * @return bool
     */
    public function isMainUser(User $user): bool
    {
        return 1 === $user->getId();
    }

    /**
     * Format raw user data.
     *
     * @param array                       $rawData
     * @param \BackBee\Security\User|null $currentUser
     *
     * @return array
     */
    public function formatRawUserData(array $rawData, User $currentUser = null): array
    {
        $isRemovable = true;

        if ($user = $this->getById($rawData['id'])) {
            if ($this->isMainUser($user) || ($currentUser && $currentUser->getId() === $user->getId())) {
                $isRemovable = false;
            }

            $rawData['created'] = $user->getCreated()->format(DATE_ATOM);
            $rawData['modified'] = $user->getModified()->format(DATE_ATOM);
            $rawData['group_types'] = array_map(static function ($group) {
                return (new Slugify())->slugify(str_replace('_name', '', $group->getName()), ['separator' => '_']);
            }, $user->getGroups()->toArray());
        }

        $rawData['is_removable'] = $isRemovable;

        unset(
            $rawData['activated'],
            $rawData['api_key_enabled'],
            $rawData['api_key_public'],
            $rawData['groups'],
            $rawData['state']
        );

        return $rawData;
    }
}
