<?php

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
