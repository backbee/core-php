<?php

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

    public function setId()
    {
        $this->id = $id;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function setPage(PageLang $page)
    {
        $this->page = $page;
    }
}