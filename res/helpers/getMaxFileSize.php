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

namespace BackBee\Renderer\Helper;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class getMaxFileSize extends AbstractHelper
{
    /**
     *
     * @var int
     */
    protected $maxFileSize;

    public function __construct()
    {
        $maxSize = $this->parseSize(ini_get('post_max_size'));

        $uploadMaxSize = $this->parseSize(ini_get('upload_max_size'));

        if ($uploadMaxSize > 0 && $uploadMaxSize < $maxSize) {
            $maxSize = $uploadMaxSize;
        }

        $this->maxFileSize = $maxSize;
    }

    public function __invoke()
    {
        return $this->maxFileSize;
    }

    protected function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            $size = $size * pow(1024, stripos('bkmgtpezy', $unit[0]));
        }

        return round($size);
    }
}
