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

namespace BackBeeCloud\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="page_redirection")
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageRedirection
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue
     *
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(name="to_redirect", type="string", length=255, nullable=false)
     *
     * @var string
     */
    protected $toRedirect;

    /**
     * @ORM\Column(name="target", type="string", length=255, nullable=false)
     *
     * @var string
     */
    protected $target;

    public function __construct($toRedirect, $target)
    {
        $this->toRedirect = $toRedirect;
        $this->target = $target;
    }

    public function toRedirect()
    {
        return $this->toRedirect;
    }

    public function target()
    {
        return $this->target;
    }
}
