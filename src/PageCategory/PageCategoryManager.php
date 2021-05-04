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

namespace BackBeeCloud\PageCategory;

use BackBee\NestedNode\Page;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageCategoryManager
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var array
     */
    private $categories = [];

    /**
     * PageCategoryManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param array                  $categoryProviders
     */
    public function __construct(EntityManagerInterface $entityManager, array $categoryProviders)
    {
        $this->entityManager = $entityManager;
        $this->initCategories($categoryProviders);
    }

    /**
     * Get categories.
     *
     * @return array
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * Has category.
     *
     * @param $category
     *
     * @return bool
     */
    public function hasCategory($category): bool
    {
        return in_array($category, $this->categories);
    }

    /**
     * Associate page and category.
     *
     * @param Page $page
     * @param      $category
     */
    public function associatePageAndCategory(Page $page, $category): void
    {
        $association = $this->getAssociationByPage($page);

        if ('none' === $category && null !== $association) {
            $this->entityManager->remove($association);
            return;
        }

        if ('none' === $category) {
            return;
        }

        $this->assertCategoryExists($category);

        if (null === $association) {
            $this->entityManager->persist(new PageCategory($page, $category));
            return;
        }

        $association->setCategory($category);
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
        $association = $this->getAssociationByPage($page);
        return $association ? $association->getCategory() : null;
    }

    /**
     * Initialize categories.
     *
     * @param array $categoryProviders
     */
    private function initCategories(array $categoryProviders): void
    {
        foreach ($categoryProviders as $provider) {
            if (!($provider instanceof CategoryProviderInterface)) {
                throw new RuntimeException(
                    sprintf(
                        'Category provider must be an instance of %s.',
                        CategoryProviderInterface::class
                    )
                );
            }

            $this->categories = array_merge($this->categories, $provider->getCategories());
        }
    }

    /**
     * Assert category exists.
     *
     * @param $category
     */
    public function assertCategoryExists($category): void
    {
        if (!$this->hasCategory($category)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Page category "%s" does not exist.',
                    $category
                )
            );
        }
    }

    /**
     * Get association by page.
     *
     * @param Page $page
     *
     * @return PageCategory|null
     */
    private function getAssociationByPage(Page $page): ?PageCategory
    {
        return $this->entityManager->getRepository(PageCategory::class)->findOneBy(['page' => $page]);
    }

    /**
     * Delete association by page.
     *
     * @param Page $page
     */
    public function deleteAssociationByPage(Page $page): void
    {
        if (null !== $association = $this->getAssociationByPage($page)) {
            $this->entityManager->remove($association);
            return;
        }
    }
}
