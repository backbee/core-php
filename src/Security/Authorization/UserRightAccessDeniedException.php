<?php

namespace BackBeeCloud\Security\Authorization;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightAccessDeniedException extends \RuntimeException
{
    public static function create()
    {
        return new self('You do not have enough right to achieve your action.');
    }
}
