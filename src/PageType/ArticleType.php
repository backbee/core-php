<?php

namespace BackBeeCloud\PageType;

use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Article\ArticleTitle;
use BackBee\ClassContent\Media\Image;
use BackBee\NestedNode\Page;
use BackBee\ClassContent\Text\Paragraph;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ArticleType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return 'Article layout';
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return 'article';
    }

    /**
     * {@inheritdoc}
     */
    public function defaultContents()
    {
        return [
            ArticleTitle::class => function(ArticleTitle $title, Page $page) {
                $title->value = $page->getTitle();
            },
            Image::class => null,
            ArticleAbstract::class => null,
            Paragraph::class => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function exclusiveClassContents()
    {
        return [ArticleTitle::class, ArticleAbstract::class];
    }
}
