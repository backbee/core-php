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

namespace BackBeePlanet\OptimizeImage;

use BackBee\BBApplication;
use BackBee\ClassContent\Basic\Image;
use BackBee\Config\Config;
use BackBee\HttpClient\UserAgent;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use function count;

/**
 * Class OptimizeImageManager
 *
 * @package BackBeePlanet\OptimizeImage
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class OptimizeImageManager
{
    public const CMD = 'convert %s%s%s';
    public const CODE_OK = 0;
    public const CODE_ERROR = 1;
    public const CMD_TRANSPARENCY_INFO = 'convert %s -format "%%[opaque]" info:';
    public const CMD_FRAMES_NUMBER = 'identify -format %%n %s';
    private const DEFAULT_SETTINGS = [
        'filter' => 'Triangle',
        'define' => 'filter:support=2',
        'unsharp' => '0.25x0.25+8+0.065',
        'dither' => 'None -posterize 136',
        'define' => 'jpeg:fancy-upsampling=off',
        'define' => 'png:compression-filter=5',
        'define' => 'png:compression-level=9',
        'define' => 'png:compression-strategy=1',
        'define' => 'png:exclude-chunk=all',
        'interlace' => 'none',
        'colorspace' => 'sRGB',
        'strip' => null,
    ];

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $mediaDir;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var Config
     */
    private $config;

    /**
     * OptimizeImageManager constructor.
     *
     * @param BBApplication $app
     * @param Config        $config
     *
     * @throws Exception
     */
    public function __construct(BBApplication $app, Config $config)
    {
        $this->filesystem = new Filesystem();
        $this->mediaDir = $app->getMediaDir();
        $this->config = $config;
        $this->settings = $this->getSettings();
    }

    /**
     * Set browser colsizes.
     *
     * @param $colsizes
     *
     * @return array
     */
    private function setBrowserColsizes($colsizes): array
    {
        ksort($colsizes, SORT_NUMERIC);
        $colsizeKeys = array_keys($this->settings['colsizes']);
        $colsizes = [];
        $colsizes['min'] = min($colsizeKeys);
        $colsizes['mid'] = OptimizeImageUtils::getMedianColsizeKey($colsizeKeys);
        $colsizes['max'] = max($colsizeKeys);

        return $colsizes;
    }

    /**
     * Get settings.
     *
     * @return array
     */
    public function getSettings(): array
    {
        $options = $this->config->getSection('optimize_image');

        if (null !== ($options['original'] ?? null) && null !== ($options['formats'] ?? null)) {
            $this->settings['original'] = $options['original'];

            // settings
            $this->settings['formats'] = [];
            $this->settings['colsizes'] = [];
            foreach ($options['formats'] as $key => $value) {
                // merge original settings with formats...
                $this->settings['formats'][$key] = array_merge(
                    self::DEFAULT_SETTINGS,
                    $this->settings['original'],
                    $value['options']
                );

                // setting up colsize...
                foreach ($value['colsizes'] as $colsize) {
                    $this->settings['colsizes'][$colsize] = $key;
                }
            }

            // set browser colsizes (for classcontent non defined explicit colsizes)
            $this->settings['browsercolsizes'] = $this->setBrowserColsizes($this->settings['colsizes']);
        }

        return $this->settings ?? [];
    }

    /**
     * Convert all images.
     *
     * @param $filepath
     */
    public function convertAllImages($filepath): void
    {
        if (
            null === ($this->settings['original'] ?? null) ||
            false === $this->filesystem->exists($filepath) ||
            1 !== preg_match('~^image/(gif|jpeg|jpg|png|bmp)$~', @mime_content_type($filepath))
        ) {
            return;
        }

        $partsFilename = pathinfo($filepath);
        $filepathOut = $partsFilename['dirname'] . '/' . $partsFilename['filename'] . '%s.%s';
        $settingsOriginal = $this->settings['original'];
        [$width] = getimagesize($filepath);

        // formats
        foreach ($this->settings['formats'] as $key => $options) {
            if ($width <= $options['resize']) {
                unset($options['resize']);
            }
            $this->convert($filepath, sprintf($filepathOut, '_' . $key, 'jpg'), $options);
        }

        if ($width <= $settingsOriginal['resize']) {
            unset($settingsOriginal['resize']);
        }

        // original always at the end
        $this->convert(
            $filepath,
            sprintf($filepathOut, '', 'jpg'),
            array_merge(self::DEFAULT_SETTINGS, $settingsOriginal)
        );
    }

    /**
     * Convert.
     *
     * @param string $filepathIn
     * @param string $filepathOut
     * @param array  $options
     *
     * @return int
     */
    private function convert(string $filepathIn, string $filepathOut, array $options): int
    {
        $cmd = sprintf(
            self::CMD,
            $filepathIn,
            $this->getConvertOptions($options),
            $filepathOut
        );

        // execute command
        exec($cmd, $output, $code);

        // error
        if (false === $this->filesystem->exists($filepathOut)) {
            return self::CODE_ERROR;
        }

        return self::CODE_OK;
    }

    /**
     * Is valid to optimize.
     *
     * @param $filePath
     *
     * @return bool
     */
    public function isValidToOptimize($filePath): bool
    {
        if (file_exists($filePath)) {
            return !(
                (1 !== preg_match('~^image/(gif|jpeg|jpg|png|bmp)$~', @mime_content_type($filePath)))
                || ('image/gif' === @mime_content_type($filePath) && (true === $this->isAnimated($filePath)))
                || ('image/png' === @mime_content_type($filePath)) && (true === $this->isTransparent($filePath))
            );
        }

        return false;
    }

    /**
     * Is transparent.
     *
     * @param string $filepath
     *
     * @return bool
     */
    private function isTransparent(string $filepath): bool
    {
        $cmd = sprintf(
            self::CMD_TRANSPARENCY_INFO,
            $filepath
        );

        // execute command
        exec($cmd, $output, $code);

        return (!json_decode(reset($output)));
    }

    /**
     * Is animated.
     *
     * @param string $filepath
     *
     * @return bool
     */
    private function isAnimated(string $filepath): bool
    {
        $cmd = sprintf(
            self::CMD_FRAMES_NUMBER,
            $filepath
        );

        // execute command
        exec($cmd, $output, $code);

        return (1 !== json_decode(reset($output)));
    }

    /**
     * Get convert options.
     *
     * @param array $options
     *
     * @return string
     */
    private function getConvertOptions(array $options): string
    {
        if (0 === count($options)) {
            return ' ';
        }

        return ' ' . (implode(
                ' ',
                array_map(
                    static function ($key) use ($options) {
                        return '-' . $key . ($options[$key] ? ' ' . $options[$key] : '');
                    },
                    array_keys($options)
                )
            )) . ' ';
    }

    //@TODO current media directory <> current web media
    public function getMediaPath($filePath): string
    {
        $filePath = preg_replace(
            '#^(https?\:)?\/\/([a-z0-9][a-z0-9\-]{0,61}[a-z0-9]\.)+[a-z0-9][a-z0-9\-]*[a-z0-9]#',
            '',
            $filePath
        );

        return $this->mediaDir . str_replace(['/media/', '/img/'], '/', $filePath);
    }

    /**
     * Returns set of images formats available for provided image
     *
     * @param Image $image
     *
     * @return array
     */
    public function getImageSet(Image $image): array
    {
        $set = ['src' => $image->image->path];

        if ($this->isValidToOptimize($this->getMediaPath($image->image->path))) {
            foreach (array_keys($this->settings['formats']) as $format) {
                $set[$format] = OptimizeImageUtils::genericSizeFilename($image->image->path, $format, 'jpg');
            }
        }

        return $set;
    }

    /**
     * Get optimize image path.
     *
     * @param string $path
     * @param bool   $inFluid
     * @param int    $colSize
     *
     * @return string
     */
    public function getOptimizeImagePath(string $path, bool $inFluid, int $colSize): string
    {
        // skipping if path is false or parameter in fluid is true or image is transparency png or animated gif...
        if (
            null === ($this->settings['colsizes'] ?? null) ||
            null === ($this->settings['browsercolsizes'] ?? null) ||
            false === $path ||
            true === $inFluid ||
            false === $this->isValidToOptimize($filePath = $this->getMediaPath($path)) ||
            ($this->checkImageHasAlreadyBeenOptimized($path) && $this->filesystem->exists($this->getMediaPath($path)))
        ) {
            return $path;
        }

        // get settings
        $colSizesSettings = $this->settings['colsizes'];
        $browserColSizesSettings = $this->settings['browsercolsizes'];

        if (UserAgent::isMobile()) {
            $size = $colSizesSettings[$browserColSizesSettings['min']];
        } elseif (null !== ($colSizesSettings[$colSize] ?? null)) {
            $size = $colSizesSettings[$colSize];
        } else {
            $size = $colSizesSettings[$browserColSizesSettings['max']];
        }

        $filename = OptimizeImageUtils::genericSizeFilename($path, $size, 'jpg');

        if (false === $this->filesystem->exists($this->getMediaPath($filename))) {
            $this->convertAllImages($filePath);
        }

        return $filename;
    }

    /**
     * Checks if the image has already been optimized.
     *
     * @param string $path
     *
     * @return bool
     */
    private function checkImageHasAlreadyBeenOptimized(string $path): bool
    {
        return 1 === preg_match(
                '/.*_[' . sprintf("'%s'", implode("','", array_keys($this->settings['formats']))) . '].*/',
                $path
            );
    }
}
