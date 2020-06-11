<?php

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
}
