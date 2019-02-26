<?php

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
