<?php

namespace BackBeeCloud\Security\GroupType;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class CannotAddOrRemoveUserToClosedGroupTypeException extends \LogicException
{
    public static function create()
    {
        return new self('Cannot add or remove user from group type when it\'s closed.');
    }
}
