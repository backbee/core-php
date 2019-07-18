<?php

namespace BackBeeCloud\Api\DataFormatter;

use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\Tag\TagLang;
use BackBee\NestedNode\KeyWord as Tag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class TagDataFormatter
{
    /**
     * @var bool
     */
    protected $isMultiLangEnabled;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $tagLangRepository;

    public function __construct(MultiLangManager $multilangManager, EntityManagerInterface $entityManager)
    {
        $this->isMultiLangEnabled = $multilangManager->isActive();
        if ($this->isMultiLangEnabled) {
            $this->tagLangRepository = $entityManager->getRepository(TagLang::class);
        }
    }

    public function format(Tag $tag)
    {
        $parents = [];
        $parent = $tag->getParent();
        while ($parent) {
            $parents[] = $parent->getKeyWord();
            $parent = $parent->getParent();
        }

        $result = [
            'uid' => $tag->getUid(),
            'keyword' => $tag->getKeyWord(),
            'has_children' => $tag->getChildren()->count() > 0,
            'parent_uid' => $tag->getParent() ? $tag->getParent()->getUid() : null,
            'parents' => array_reverse($parents),
            'created' => $tag->getCreated() ? $tag->getCreated()->getTimestamp() : null,
            'modified' => $tag->getModified() ? $tag->getModified()->getTimestamp() : null,
        ];

        if ($this->isMultiLangEnabled) {
            $translations = [];
            foreach ($this->tagLangRepository->findBy(['tag' => $tag]) as $tagLang) {
                $translations[$tagLang->getLang()->getLang()] = $tagLang->getTranslation();
            }

            $result['translations'] = $translations;
        }

        return $result;
    }
}
