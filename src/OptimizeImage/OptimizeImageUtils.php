<?php

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
