<?php

namespace BackBeeCloud\Security\User;

use BackBee\Security\User;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class UserDataFormatter
{
    public static function format(User $user)
    {
        return [
            'id' => $user->getId(),
            'login' => $user->getLogin(),
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstName(),
            'lastname' => $user->getLastName(),
            'created' => $user->getCreated()->format(DATE_ATOM),
            'modified' => $user->getModified()->format(DATE_ATOM),
        ];
    }
}
