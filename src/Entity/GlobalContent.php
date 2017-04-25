<?php

namespace BackBeeCloud\Entity;

use BackBee\ClassContent\AbstractClassContent;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="global_content")
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class GlobalContent
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
     * @ORM\OneToOne(targetEntity="BackBee\ClassContent\AbstractClassContent")
     * @ORM\JoinColumn(name="content_uid", referencedColumnName="uid", nullable=false, unique=true)
     *
     * @var AbstractClassContent
     */
    protected $content;

    public function __construct(AbstractClassContent $content)
    {
        $this->content = $content;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getContent()
    {
        return $this->content;
    }
}
