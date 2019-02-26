<?php

namespace BackBeeCloud\PageCategory;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageCategoryManagerFactory
{
    const PAGE_CATEGORY_PROVIDER_SERVICE_TAG = 'page_category.provider';

    public static function createPageCategoryManager(ContainerBuilder $dic, EntityManagerInterface $entityManager)
    {
        $categoryProviders = [];
        foreach ($dic->findTaggedServiceIds(self::PAGE_CATEGORY_PROVIDER_SERVICE_TAG) as $serviceId => $data) {
            if ($dic->has($serviceId)) {
                $categoryProviders[] = $dic->get($serviceId);
            }
        }

        return new PageCategoryManager($entityManager, $categoryProviders);
    }
}
