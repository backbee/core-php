<?php

namespace BackBeeCloud\Security\GroupType;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class CannotUpdateReadOnlyGroupTypeException extends \LogicException
{
    public static function create()
    {
        return new self('Cannot update ready only group type.');
    }
}
