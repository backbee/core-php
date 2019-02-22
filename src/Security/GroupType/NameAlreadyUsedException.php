<?php

namespace BackBeeCloud\Security\GroupType;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class NameAlreadyUsedException extends \LogicException
{
    public static function create($name)
    {
        return new self(
            sprintf(
                'Group type name "%s" is already used.',
                $name
            )
        );
    }
}
