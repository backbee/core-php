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

namespace BackBeePlanet\Importer;

use InvalidArgumentException;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface ReaderInterface
{
    /**
     * Returns reader name.
     *
     * @return string
     */
    public function name();

    /**
     * Verifies if the reader is well configured and ready to collect rows.
     *
     * @param mixed $source
     *
     * @throws InvalidArgumentException if the reader is not enable to fetch data
     */
    public function verify($source);

    /**
     * Returns an iterable collection.
     *
     * @param mixed $source
     *
     * @return mixed
     */
    public function collect($source);

    /**
     * Returns metadata about import (max item, max page, etc.).
     *
     * @param mixed $source
     *
     * @return array
     */
    public function sourceMetadata($source);

    /**
     * Returns true if the provided type is supported by current reader. Else false.
     *
     * @param string $type
     *
     * @return bool
     */
    public function supports($type);
}
