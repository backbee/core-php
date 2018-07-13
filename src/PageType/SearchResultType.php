<?php

namespace BackBeeCloud\PageType;

use BackBee\ClassContent\Basic\SearchResult;
use BackBee\ClassContent\Basic\Title;
use BackBee\NestedNode\Page;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchResultType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return 'Search result layout';
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
    public function uniqueName()
    {
        return 'search_result';
    }

    /**
     * {@inheritdoc}
     */
    public function isDumpable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultContents()
    {
        return [
            Title::class => function(Title $title, Page $page) {
                $title->value = $page->getTitle();
            },
            SearchResult::class => null,
        ];
    }
}
