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

namespace BackBee\FileSystem;

use BackBee\BBApplication;
use BackBee\ClassContent\Basic\Image;
use Exception;
use RuntimeException;

/**
 * Class ImageFilesystemHandler
 *
 * @package BackBee\FileSystem
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImageFilesystemHandler implements ImageHandlerInterface
{
    public const MEDIA_BASE_URI = '/media/';

    /**
     * @var string
     */
    protected $mediaDir;

    /**
     * ImageFilesystemHandler constructor.
     *
     * @param BBApplication $app
     *
     * @throws Exception
     */
    public function __construct(BBApplication $app)
    {
        $this->mediaDir = $app->getMediaDir();
    }

    /**
     * {@inheritdoc}
     */
    public function upload(string $filename, string $filepath, bool $removeFile = true): ?string
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return null;
        }

        return $this->runUpload($filename, $filepath, $removeFile);
    }

    /**
     * {@inheritdoc}
     */
    public function uploadFromUrl(string $url, ?string $filename = null): ?string
    {
        $newPath = null;
        if (false !== $rawContent = file_get_contents($url)) {
            if (false === $filename) {
                $filename = basename($url);
            }

            touch($tmpfile = sprintf('%s/%s', sys_get_temp_dir(), $filename));

            file_put_contents($tmpfile, $rawContent);

            $newPath = $this->runUpload($filename, $tmpfile);
        }

        return $newPath;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path, bool $throwException = false): void
    {
        $filepath = str_replace(
            ['\\/', '"', static::MEDIA_BASE_URI],
            ['/', '', $this->mediaDir . '/'],
            $path
        );
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function duplicate(string $path, string $newName): ?string
    {
        $filepath = str_replace(
            ['\\/', '"', static::MEDIA_BASE_URI],
            ['/', '', $this->mediaDir . '/'],
            $path
        );
        if (!file_exists($filepath)) {
            return null;
        }

        copy($filepath, $this->mediaDir . DIRECTORY_SEPARATOR . $newName);

        return static::MEDIA_BASE_URI . $newName;
    }

    /**
     * Performs the real upload.
     *
     * @param string  $filename
     * @param string  $filepath
     * @param boolean $removeFile
     *
     * @return null|string
     */
    protected function runUpload(string $filename, string $filepath, bool $removeFile = true): ?string
    {
        $newName = $this->mediaDir . DIRECTORY_SEPARATOR . $filename;
        $this->mkdirOnce(dirname($newName));
        $result = copy($filepath, $newName);
        if (false === $result) {
            throw new RuntimeException(
                sprintf(
                    '[%s] failed to rename %s to %s.',
                    __METHOD__,
                    $filepath,
                    $newName
                )
            );
        } elseif (true === $removeFile) {
            unlink($filepath);
        }

        return static::MEDIA_BASE_URI . $filename;
    }

    /**
     * Mkdir once.
     *
     * @param $path
     */
    private function mkdirOnce($path): void
    {
        $umask = umask();
        umask(0);
        if (!is_dir($path) && !mkdir($path, 0777)) {
            throw new RuntimeException(sprintf('Error occurs while creating "%s".', $path));
        }

        umask($umask);
    }
}
