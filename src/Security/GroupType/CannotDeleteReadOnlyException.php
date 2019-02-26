<?php

namespace BackBeeCloud\Security\GroupType;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class CannotDeleteReadOnlyException extends \LogicException
{
    public static function create()
    {
        return new self('Cannot delete read only group type.');
    }
}
