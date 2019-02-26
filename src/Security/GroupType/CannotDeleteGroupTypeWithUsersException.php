<?php

namespace BackBeeCloud\Security\GroupType;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class CannotDeleteGroupTypeWithUsersException extends \LogicException
{
    public static function create()
    {
        return new self('Cannot delete group type that contains at least one user.');
    }
}
