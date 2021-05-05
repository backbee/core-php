<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

namespace BackBee\Page;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Article\ArticleTitle;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Basic\Title;
use BackBee\ClassContent\Media\Video;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\NestedNode\Page;
use BackBee\Security\SecurityContext;
use BackBee\Security\Token\BBUserToken;
use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Entity\PageTag;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeInterface;
use BackBeeCloud\PageType\TypeManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class PageContentManager
 *
 * @package BackBee\Page
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageContentManager
{
    /**
     * @var EntityManagerInterface
     */
    public $entityManager;

    /**
     * @var ContentManager
     */
    public $contentManager;

    /**
     * @var BBUserToken|null
     */
    public $bbToken;

    /**
     * @var TypeManager
     */
    public $pageTypeManager;

    /**
     * @var PageCategoryManager
     */
    public $pageCategoryManager;

    /**
     * PageContentManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param ContentManager         $contentManager
     * @param SecurityContext        $securityContext
     * @param TypeManager            $pageTypeManager
     * @param PageCategoryManager    $pageCategoryManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ContentManager $contentManager,
        SecurityContext $securityContext,
        TypeManager $pageTypeManager,
        PageCategoryManager $pageCategoryManager
    ) {
        $this->entityManager = $entityManager;
        $this->contentManager = $contentManager;
        $this->bbToken = $securityContext->getToken() instanceof BBUserToken ? $securityContext->getToken() : null;
        $this->pageTypeManager = $pageTypeManager;
        $this->pageCategoryManager = $pageCategoryManager;
    }

    /**
     * Extract title form page.
     *
     * @param Page $page
     *
     * @return string
     */
    public function extractTitleFromPage(Page $page): string
    {
        return $page->getTitle();
    }

    /**
     * Get first heading form page.
     *
     * @param Page $page
     *
     * @return string
     */
    public function getFirstHeadingFromPage(Page $page): string
    {
        $contentIds = $this->contentManager->getUidsFromPage($page, $this->bbToken);
        $title = $this->getRealFirstContentByUid(
            [
                $this->entityManager->getRepository(ArticleTitle::class)->findOneBy(['_uid' => $contentIds]),
                $this->entityManager->getRepository(Title::class)->findOneBy(['_uid' => $contentIds]),
            ],
            $contentIds
        );

        return $title ? trim(strip_tags($title->value)) : '';
    }

    /**
     * Extract abstract uid from page.
     *
     * @param Page $page
     *
     * @return null|string
     */
    public function extractAbstractUidFromPage(Page $page): ?string
    {
        $contentUid = $this->contentManager->getUidsFromPage($page, $this->bbToken);
        $abstract = $this->getRealFirstContentByUid(
            $this->entityManager->getRepository(ArticleAbstract::class)->findBy(
                [
                    '_uid' => $contentUid,
                ]
            ),
            $contentUid
        );

        $abstract = $abstract ?: null;
        if ($abstract === null) {
            $abstract = $this->getRealFirstContentByUid(
                $this->entityManager->getRepository(Paragraph::class)->findBy(
                    [
                        '_uid' => $contentUid,
                    ]
                ),
                $contentUid
            );
        }

        return $abstract ? $abstract->getUid() : null;
    }

    /**
     * Extract image uid from page.
     *
     * @param Page $page
     *
     * @return string|null
     */
    public function extractImageUidFromPage(Page $page): ?string
    {
        $contents = $this->contentManager->getUidsFromPage($page, $this->bbToken);
        $media = $this->getRealFirstContentByUid(
            array_merge(
                $this->entityManager->getRepository(Image::class)->findBy(
                    [
                        '_uid' => $contents,
                    ]
                ),
                $this->entityManager->getRepository(Video::class)->findBy(
                    [
                        '_uid' => $contents,
                    ]
                )
            ),
            $contents
        );

        if ($media instanceof Video) {
            if ($media->thumbnail->image->path === false || AbstractClassContent::STATE_NORMAL !== $media->getState()) {
                $contents = array_filter(
                    $contents,
                    static function ($uid) use ($media) {
                        return $uid !== $media->getUid();
                    }
                );
                $media = $this->getRealFirstContentByUid(
                    $this->entityManager->getRepository(Image::class)->findBy(
                        [
                            '_uid' => $contents,
                        ]
                    ),
                    $contents
                );
            }
        }

        return $media ? $media->getUid() : null;
    }

    /**
     * Extract texts form page.
     *
     * @param Page $page
     *
     * @return string
     */
    public function extractTextsFromPage(Page $page): string
    {
        $contents = $this->contentManager->getUidsFromPage($page, $this->bbToken);

        $titles = array_merge(
            $this->entityManager->getRepository(Title::class)->findBy(
                [
                    '_uid' => $contents,
                ]
            ),
            $this->entityManager->getRepository(ArticleTitle::class)->findBy(
                [
                    '_uid' => $contents,
                ]
            )
        );

        $result = [];
        foreach ($titles as $title) {
            $title->setDraft(null);
            $result[] = $title->value;
        }

        $abstracts = $this->entityManager->getRepository(ArticleAbstract::class)->findBy(
            [
                '_uid' => $contents,
            ]
        );

        foreach ($abstracts as $abstract) {
            $abstract->setDraft(null);
            $result[] = $abstract->value;
        }

        $paragraphs = $this->entityManager->getRepository(Paragraph::class)->findBy(
            [
                '_uid' => $contents,
            ]
        );

        foreach ($paragraphs as $paragraph) {
            $paragraph->setDraft(null);
            $result[] = $paragraph->value;
        }

        return $this->cleanText(implode(' ', $result));
    }

    /**
     * Clean text.
     *
     * @param $text
     *
     * @return string
     */
    public function cleanText($text): string
    {
        return trim(preg_replace('#\s\s+#', ' ', preg_replace('#<[^>]+>#', ' ', $text)));
    }

    /**
     * Get real first content by uid.
     *
     * @param array $contents
     * @param array $orders
     *
     * @return AbstractClassContent|null
     */
    public function getRealFirstContentByUid(array $contents, array $orders): ?AbstractClassContent
    {
        $firstContent = array_pop($contents);

        if ($firstContent instanceof AbstractClassContent) {
            $curPos = array_search($firstContent->getUid(), $orders, true);
            foreach ($contents as $content) {
                if (null !== $content && $curPos > $pos = array_search($content->getUid(), $orders, true)) {
                    $curPos = $pos;
                    $firstContent = $content;
                }
            }
        }

        return $firstContent;
    }

    /**
     * Has draft contents
     *
     * @param Page $page
     *
     * @return bool
     */
    public function hasDraftContents(Page $page): bool
    {
        return $this->bbToken && $this->contentManager->isDraftedPage($page, $this->bbToken);
    }

    /**
     * Get type by page.
     *
     * @param Page $page
     *
     * @return TypeInterface|null
     */
    public function getTypeByPage(Page $page): ?TypeInterface
    {
        return $this->pageTypeManager->findByPage($page);
    }

    /**
     * Get category by page.
     *
     * @param Page $page
     *
     * @return string|null
     */
    public function getCategoryByPage(Page $page): ?string
    {
        return $this->pageCategoryManager->getCategoryByPage($page);
    }

    /**
     * Get images by page.
     *
     * @param Page $page
     *
     * @return array
     */
    public function getImagesByPage(Page $page): array
    {
        return $this->contentManager->getAllImageForAnPage($page);
    }

    /**
     * Get tags by page.
     *
     * @param Page $page
     *
     * @return array
     */
    public function getTagsByPage(Page $page): array
    {
        $tags = [];

        $pageTag = $this->entityManager->getRepository(PageTag::class)->findOneBy(['page' => $page]);

        foreach ($pageTag !== null ? $pageTag->getTags() : [] as $tag) {
            $tags[] = strtolower($tag->getKeyWord());
        }

        return $tags;
    }
}
