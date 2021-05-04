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

namespace BackBeeCloud\Structure;

use BackBee\ClassContent\AbstractClassContent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface ContentHandlerInterface
{
    /**
     * Handles hydratation of provided content with passed data.
     *
     * @param  AbstractClassContent $content
     * @param  array                $data
     */
    public function handle(AbstractClassContent $content, array $data);

    /**
     *
     * Build config probided by content
     *
     * @param AbstractClassContent $content
     * @param array                $data
     */
    public function handleReverse(AbstractClassContent $content, array $data = []);

    /**
     * Returns true if the provided content is supported by current content handler.
     *
     * @param  AbstractClassContent $content
     * @return boolean
     */
    public function supports(AbstractClassContent $content);
}
