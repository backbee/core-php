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

namespace BackBeeCloud\Utils;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface YamlWriterInterface
{
    /**
     * Tries to write the provided content at the provided path in Yaml format.
     *
     * @param string $path
     * @param mixed  $content
     * @param bool   $override
     */
    public function write($path, $content, $override = true);
}
