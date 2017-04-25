<?php

namespace BackBeeCloud\PageType;

use BackBee\ClassContent\Basic\Title;
use BackBee\NestedNode\Page;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class BlankType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return 'Blank layout';
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return 'blank';
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
        ];
    }
}
