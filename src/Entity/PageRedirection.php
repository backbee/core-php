<?php

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
