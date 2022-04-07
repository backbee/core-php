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

namespace BackBeeCloud\PageCategory;

use BackBee\NestedNode\Page;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="page_category")
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageCategory
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
     * @ORM\OneToOne(targetEntity="BackBee\NestedNode\Page", cascade={"persist"})
     * @ORM\JoinColumn(name="page_uid", referencedColumnName="uid", nullable=false)
     *
     * @var Page
     */
    private $page;

    /**
     * @ORM\Column(name="category", type="string", length=64)
     *
     * @var string
     */
    private $category;

    /**
     * PageCategory constructor.
     *
     * @param Page $page
     * @param      $category
     */
    public function __construct(Page $page, $category)
    {
        $this->page = $page;
        $this->category = $category;
    }

    /**
     * @return Page
     */
    public function getPage(): Page
    {
        return $this->page;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @param $category
     */
    public function setCategory($category): void
    {
        $this->category = $category;
    }
}
