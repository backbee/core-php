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

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Title;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ParagraphHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!$this->supports($content)) {
            return;
        }

        if (isset($data['text'])) {

            $content->value = (string) $data['text'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $data = [])
    {
        $result = isset($data['current_data']) ? $data['current_data'] : [];

        $result['text'] = $content->value;
        if (
            !isset($result['parameters']['bg_color'])
            || 1 !== preg_match('~^color\-[a-z0-9]+$~', $result['parameters']['bg_color'])
        ) {
            $result['parameters']['bg_color'] = '';
        }

        if (false == $result['parameters']['bg_color']) {
            unset($result['parameters']['bg_color']);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return
            $content instanceof Paragraph
            || $content instanceof Title
            || $content instanceof ArticleAbstract
        ;
    }
}
