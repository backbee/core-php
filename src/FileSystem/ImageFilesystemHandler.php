<?php

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
    public const MEDIA_BASE_URI = '/img/';
    #const MEDIA_URI_REGEX = '~^' . self::MEDIA_BASE_URI . '[a-f0-9]{32}\.~';

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
    public function upload($filename, $filepath, bool $removeFile = true): ?string
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return null;
        }

        return $this->runUpload($filename, $filepath, $removeFile);
    }

    /**
     * {@inheritdoc}
     */
    public function uploadFromUrl($url, $filename = null): ?string
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
    public function delete($path, bool $throwException = false): void
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
    public function duplicate($path, $newName): ?string
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
