<?php

namespace BackBeeCloud\Tag;

use BackBeeCloud\Entity\Lang;
use BackBee\NestedNode\KeyWord as Tag;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="tag_lang")
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class TagLang
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
     * @ORM\ManyToOne(targetEntity="BackBee\NestedNode\KeyWord")
     * @ORM\JoinColumn(name="tag_uid", referencedColumnName="uid", nullable=false)
     *
     * @var Tag
     */
    protected $tag;

    /**
     * @ORM\ManyToOne(targetEntity="BackBeeCloud\Entity\Lang")
     * @ORM\JoinColumn(name="lang", referencedColumnName="lang", nullable=false)
     *
     * @var string
     */
    protected $lang;

    /**
     * @ORM\Column(name="translation", type="string", length=255, nullable=false)
     *
     * @var string
     */
    protected $translation;

    public function __construct(Tag $tag, Lang $lang, $translation)
    {
        $this->tag = $tag;
        $this->lang = $lang;
        $this->setTranslation($translation);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTag()
    {
        return $this->tag;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function getTranslation()
    {
        return $this->translation;
    }

    public function setTranslation($translation)
    {
        $translation = trim($translation);
        if (false == $translation) {
            throw new \RuntimeExeption('Tag translation cannot be an empty string.');
        }

        $this->translation = $translation;
    }
}
