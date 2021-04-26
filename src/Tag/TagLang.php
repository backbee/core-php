<?php

namespace BackBeeCloud\Tag;

use BackBee\NestedNode\KeyWord as Tag;
use BackBeeCloud\Entity\Lang;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Class TagLang
 *
 * @ORM\Entity
 * @ORM\Table(name="tag_lang")
 *
 * @package BackBeeCloud\Tag
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
     * @var Lang
     */
    protected $lang;

    /**
     * @ORM\Column(name="translation", type="string", length=255, nullable=false)
     *
     * @var string
     */
    protected $translation;

    /**
     * TagLang constructor.
     *
     * @param Tag  $tag
     * @param Lang $lang
     * @param      $translation
     */
    public function __construct(Tag $tag, Lang $lang, $translation)
    {
        $this->tag = $tag;
        $this->lang = $lang;
        $this->setTranslation($translation);
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get tag.
     *
     * @return Tag
     */
    public function getTag(): Tag
    {
        return $this->tag;
    }

    /**
     * Get lang.
     *
     * @return Lang
     */
    public function getLang(): Lang
    {
        return $this->lang;
    }

    /**
     * Get translation.
     *
     * @return string
     */
    public function getTranslation(): string
    {
        return $this->translation;
    }

    /**
     * Set translation.
     *
     * @param $translation
     */
    public function setTranslation($translation): void
    {
        $translation = trim($translation);
        if (false === $translation) {
            throw new InvalidArgumentException('Tag translation cannot be an empty string.');
        }

        $this->translation = $translation;
    }
}
