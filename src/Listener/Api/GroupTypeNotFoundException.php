<?php

namespace BackBeeCloud\Listener\Api;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class GroupTypeNotFoundException extends \InvalidArgumentException
{
    public static function create($id)
    {
        return new self(
            sprintf(
                'Group type with id "%s" not found.',
                $id
            )
        );
    }
}
