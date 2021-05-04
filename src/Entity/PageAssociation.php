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

use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\Entity\Lang;
use BackBee\NestedNode\Page;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="page_association", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="unique_page_idx", columns={"page_uid"})
 * })
 *
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageAssociation
{
    /**
     * @ORM\ManyToOne(targetEntity="PageLang")
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     *
     * @var PageLang
     */
    protected $id;

    /**
     *
     * @ORM\Id
     * @ORM\OneToOne(targetEntity="BackBee\NestedNode\Page")
     * @ORM\JoinColumn(name="page_uid", referencedColumnName="uid", nullable=false)
     *
     * @var Page
     */
    protected $page;

    public function __construct(PageLang $id, Page $page)
    {
        $this->id   = $id;
        $this->page = $page;
    }

    public function getId()
    {
        return $this->id;
    }

    public function updateId(PageLang $id)
    {
        $this->id = $id;
    }

    public function getPage()
    {
        return $this->page;
    }
}
