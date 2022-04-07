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

use BackBee\NestedNode\KeyWord;
use BackBee\NestedNode\Page;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="page_tag", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="unique_page_idx", columns={"page_uid"})
 * })
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageTag
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
     * @ORM\ManyToMany(targetEntity="BackBee\NestedNode\KeyWord")
     * @ORM\JoinTable(name="page_tag_keyword",
     *   joinColumns={
     *     @ORM\JoinColumn(name="page_tag_id", referencedColumnName="id")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="tag_uid", referencedColumnName="uid")
     *   }
     * )
     *
     * @var ArrayCollection
     */
    protected $tags;

    public function __construct(Page $page)
    {
        $this->page = $page;
        $this->tags = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function addTag(KeyWord $keyword)
    {
        if (!$this->tags->contains($keyword)) {
            $this->tags->add($keyword);
        }

        return $this;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function resetTags()
    {
        $this->tags->clear();

        return $this;
    }

    public function setTags(ArrayCollection $tags)
    {
        $this->tags = $tags;

        return $this;
    }
}
