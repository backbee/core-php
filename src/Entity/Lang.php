<?php

namespace BackBeeCloud\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="lang")
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class Lang
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=2)
     *
     * @var string
     */
    protected $lang;

    /**
     * @ORM\Column(name="is_active", type="boolean")
     *
     * @var string
     */
    protected $isActive = false;

    /**
     * @ORM\Column(name="`default`", type="boolean")
     *
     * @var string
     */
    protected $default = false;

    public function __construct($lang)
    {
        if (2 !== strlen($lang)) {
            throw new \InvalidArgumentException('Lang length must be equal to 2.');
        }

        $this->lang = $lang;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function isActive()
    {
        return $this->isActive;
    }

    public function enable()
    {
        $this->isActive = true;
    }

    public function disable()
    {
        $this->isActive = false;
    }

    public function isDefault()
    {
        return $this->default;
    }
}
