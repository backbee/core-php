<?php

namespace BackBeeCloud\PageType;

use BackBeeCloud\PageType\AbstractType;
use BackBee\ClassContent\Basic\PageByTagResult;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageByTagResultType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return 'Pages by tag result';
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return 'page_by_tag_result';
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDuplicable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isRemovable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isPullable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultContents()
    {
        return [
            PageByTagResult::class => null,
        ];
    }
}

