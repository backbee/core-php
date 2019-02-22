<?php

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
