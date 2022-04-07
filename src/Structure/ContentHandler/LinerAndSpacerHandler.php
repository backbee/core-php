<?php

/*
 * Copyright (c) 2022 Obione
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
use BackBee\ClassContent\Basic\Liner;
use BackBee\ClassContent\Basic\Spacer;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class LinerAndSpacerHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        // nothing specific to do...
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $data = [])
    {
        $result = isset($data['current_data']) ? $data['current_data'] : [];
        $result['parameters']['height'] = 26;

        if (isset($result['parameters']['height']) && 40 > $result['parameters']['height']) {
            unset($result['parameters']['height']);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Liner || $content instanceof Spacer;
    }
}
