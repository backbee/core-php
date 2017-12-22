<?php

namespace BackBeeCloud\User;

use BackBee\Security\User;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserManager
{
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
