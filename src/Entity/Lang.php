<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBeeCloud\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="lang")
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class Lang
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=2)
     *
     * @var string
     */
    protected $lang;

    /**
     * @ORM\Column(name="is_active", type="boolean")
     *
     * @var string
     */
    protected $isActive = false;

    /**
     * @ORM\Column(name="`default`", type="boolean")
     *
     * @var string
     */
    protected $default = false;

    public function __construct($lang)
    {
        if (2 !== strlen($lang)) {
            throw new \InvalidArgumentException('Lang length must be equal to 2.');
        }

        $this->lang = $lang;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function isActive()
    {
        return $this->isActive;
    }

    public function enable()
    {
        $this->isActive = true;
    }

    public function disable()
    {
        $this->isActive = false;
    }

    public function isDefault()
    {
        return $this->default;
    }
}
