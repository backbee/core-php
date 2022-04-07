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

namespace BackBeeCloud\Api\DataFormatter;

use BackBee\NestedNode\KeyWord as Tag;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\Tag\TagLang;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tag data formatter.
 *
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

    /**
     * @param \BackBeeCloud\MultiLang\MultiLangManager $multiLangManager
     * @param \Doctrine\ORM\EntityManagerInterface     $entityManager
     */
    public function __construct(MultiLangManager $multiLangManager, EntityManagerInterface $entityManager)
    {
        $this->isMultiLangEnabled = $multiLangManager->isActive();
        if ($this->isMultiLangEnabled) {
            $this->tagLangRepository = $entityManager->getRepository(TagLang::class);
        }
    }

    /**
     * Formatted tag
     *
     * @param \BackBee\NestedNode\KeyWord $tag
     *
     * @return array
     */
    public function format(Tag $tag): array
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
