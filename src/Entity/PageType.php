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

use BackBee\NestedNode\Page;
use BackBeeCloud\PageType\TypeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="page_type", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="unique_page_idx", columns={"page_uid"})
 * })
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageType
{
    /**
     * @ORM\Id
     * @ORM\Column(type="bigint", options={"unsigned": true})
     * @ORM\GeneratedValue
     *
     * @var int
     */
    protected $id;

    /**
     * @ORM\OneToOne(targetEntity="BackBee\NestedNode\Page")
     * @ORM\JoinColumn(name="page_uid", referencedColumnName="uid", nullable=false)
     *
     * @var Page
     */
    protected $page;

    /**
     * @ORM\Column(name="type_unique_name", type="string", length=64)
     *
     * @var string
     */
    protected $typeName;

    /**
     * @var TypeInterface
     */
    protected $type;

    public function __construct(Page $page, TypeInterface $type)
    {
        $this->page = $page;
        $this->setType($type);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType(TypeInterface $type)
    {
        $this->type = $type;
        $this->typeName = $type->uniqueName();

        return $this;
    }

    public function getTypeName()
    {
        return $this->typeName;
    }
}
