<?php

namespace BackBeeCloud\PageCategory;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface CategoryProviderInterface
{
    /**
     * Returns an array of string, each one represent a category.
     *
     * @return array
     */
    public function getCategories();
}
