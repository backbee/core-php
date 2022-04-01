<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBeeCloud\Security\User;

use BackBee\Security\User;
use Cocur\Slugify\Slugify;

/**
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class UserDataFormatter
{
    /**
     * User data formatter.
     *
     * @param \BackBee\Security\User      $user
     * @param null|\BackBee\Security\User $bbUser
     *
     * @return array
     */
    public static function format(User $user, ?User $bbUser = null): array
    {
        return [
            'id' => $user->getId(),
            'login' => $user->getLogin(),
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstName(),
            'lastname' => $user->getLastName(),
            'group_types' => array_map(static function ($group) {
                return (new Slugify())->slugify(str_replace('_name', '', $group->getName()), ['separator' => '_']);
            }, $user->getGroups()->toArray()),
            'created' => $user->getCreated()->format(DATE_ATOM),
            'modified' => $user->getModified()->format(DATE_ATOM),
            'is_removable' => !(1 === $user->getId() || ($bbUser && $bbUser->getId() === $user->getId())),
        ];
    }
}
