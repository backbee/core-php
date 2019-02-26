<?php

namespace BackBeeCloud\PageCategory;

use BackBee\NestedNode\Page;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
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

    public function __construct(EntityManagerInterface $entityManager, array $categoryProviders)
    {
        $this->entityManager = $entityManager;

        $this->initCategories($categoryProviders);
    }

    public function getCategories()
    {
        return $this->categories;
    }

    public function hasCategory($category)
    {
        return in_array($category, $this->categories);
    }

    public function associatePageAndCategory(Page $page, $category)
    {
        $this->assertCategoryExists($category);

        if (null === $association = $this->getAssociationByPage($page)) {
            $this->entityManager->persist(
                new PageCategory(
                    $page,
                    $category
                )
            );

            return;
        }

        $association->setCategory($category);
    }

    public function getCategoryByPage(Page $page)
    {
        $association = $this->getAssociationByPage($page);

        return $association
            ? $association->getCategory()
            : null
        ;
    }

    private function initCategories(array $categoryProviders)
    {
        foreach ($categoryProviders as $provider) {
            if (!($provider instanceof CategoryProviderInterface)) {
                throw new \RuntimeException(
                    sprintf(
                        'Category provider must be an instance of %s.',
                        CategoryProviderInterface::class
                    )
                );
            }

            $this->categories = array_merge(
                $this->categories,
                $provider->getCategories()
            );
        }
    }

    public function assertCategoryExists($category)
    {
        if (!$this->hasCategory($category)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Page category "%s" does not exist.',
                    $category
                )
            );
        }
    }

    private function getAssociationByPage(Page $page)
    {
        return $this->entityManager->getRepository(PageCategory::class)->findOneBy(['page' => $page]);
    }
}
