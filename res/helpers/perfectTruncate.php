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

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class perfectTruncate extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @param $text
     * @param $maxLength
     *
     * @return string
     */
    public function __invoke($text, $maxLength): string
    {
        $cleaned = html_entity_decode(strip_tags($text));
        if (strlen($cleaned) < $maxLength) {
            return $cleaned;
        }

        $result = $cleaned;
        $length = $maxLength;
        if (false !== ($breakpoint = strpos($result, ' ', $maxLength))) {
            $length = $breakpoint;
        }

        $result = rtrim(substr($result, 0, $length));

        if (strlen($result) > $maxLength) {
            $pieces = explode(' ', $result);
            array_pop($pieces);
            $result = implode(' ', $pieces);
        }

        return $result . '…';
    }
}
