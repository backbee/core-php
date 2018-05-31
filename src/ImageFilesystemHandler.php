<?php

namespace BackBeeCloud;

use BackBeeCloud\Entity\ContentDuplicatePreSaveEvent;
use BackBeeCloud\ImageHandlerInterface;
use BackBeePlanet\GlobalSettings;
use BackBee\BBApplication;
use BackBee\ClassContent\Basic\Image;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImageFilesystemHandler implements ImageHandlerInterface
{
    const MEDIA_BASE_URI = '/img/';
    const MEDIA_URI_REGEX = '~^' . self::MEDIA_BASE_URI . '[a-f0-9]{32}\.~';

    /**
     * @var string
     */
    protected $mediaDir;

    public function __construct(BBApplication $app)
    {
        $this->mediaDir = $app->getMediaDir();
    }

    /**
     * {@inheritdoc}
     */
    public function upload($filename, $filepath, $removeFile = true)
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return null;
        }

        return $this->runUpload($filename, $filepath, $removeFile);
    }

    /**
     * {@inheritdoc}
     */
    public function uploadFromUrl($url, $filename = null)
    {
        $newPath = null;
        if (false !== $rawContent = file_get_contents($url)) {
            if (false == $filename) {
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
    public function delete($path, $throwException = false)
    {
        $filepath = str_replace(
            static::MEDIA_BASE_URI,
            $this->mediaDir . '/',
            str_replace(['\\/', '"'], ['/', ''], $path)
        );
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function duplicate($path, $newName)
    {
        $filepath = str_replace(
            static::MEDIA_BASE_URI,
            $this->mediaDir . '/',
            str_replace(['\\/', '"'], ['/', ''], $path)
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
     * @param  string  $filename
     * @param  string  $filepath
     * @param  boolean $removeFile
     * @return null|string
     */
    protected function runUpload($filename, $filepath, $removeFile = true)
    {
        $newname = $this->mediaDir . DIRECTORY_SEPARATOR . $filename;
        $result = copy($filepath, $newname);
        if (false === $result) {
            throw new \RuntimeException(sprintf(
                '[%s] failed to rename %s to %s.',
                __METHOD__,
                $filepath,
                $newname
            ));
        } elseif (true === $removeFile) {
            unlink($filepath);
        }

        return static::MEDIA_BASE_URI . $filename;
    }
}
