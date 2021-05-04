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

namespace BackBeeCloud\PageType;

use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Article\ArticleTitle;
use BackBee\ClassContent\Basic\Image;
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
