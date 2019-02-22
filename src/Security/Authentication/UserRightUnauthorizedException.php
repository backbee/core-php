<?php

namespace BackBeeCloud\Security\Authentication;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightUnauthorizedException extends \RuntimeException
{
    public static function create()
    {
        return new self('You must be authenticated to complete this action.');
    }
}
