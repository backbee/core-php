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

namespace BackBeePlanet\OptimizeImage;

/**
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class OptimizeImageUtils
{
    /**
     * Generic size filename.
     *
     * @param $filename
     * @param $size
     * @param $ext
     *
     * @return string|string[]|null
     */
    public static function genericSizeFilename($filename, $size, $ext)
    {
        return preg_replace('~\.([a-z]+)$~i', sprintf('_%s.' . '%s', $size, $ext), $filename);
    }

    /**
     * Replace upload data extension.
     *
     * @param $imageData
     * @param $ext
     *
     * @return array
     */
    public static function replaceUploadDataExtension($imageData, $ext): array
    {
        $data = [];
        foreach ($imageData as $key => $value) {
            $data[$key] = preg_replace('~\.([a-z]+)$~i', '.' . $ext, $value);
            if ($key === 'path') {
                $data[$key] = preg_replace(
                    '#^(https?\:)?\/\/([a-z0-9][a-z0-9\-]{0,61}[a-z0-9]\.)+[a-z0-9][a-z0-9\-]*[a-z0-9]#',
                    '',
                    $data[$key]
                );
            }
        }

        return $data;
    }

    /**
     * Get median colsize key.
     *
     * @param $colsize
     *
     * @return mixed
     */
    public static function getMedianColsizeKey($colsize)
    {
        if (($c = count($colsize)) % 2 === 0) {
            return $colsize[$c / 2 - 1];
        }

        return $colsize[floor($c / 2)];
    }
}
