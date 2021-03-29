<?php

namespace BackBee\FileSystem;

/**
 * Interface ImageHandlerInterface
 *
 * @package BackBee\FileSystem
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface ImageHandlerInterface
{
    /**
     * Uploads file located at provided filepath and returns the generated path.
     *
     * @param string  $filename
     * @param string  $filepath
     * @param boolean $removeFile
     *
     * @return null|string
     */
    public function upload(string $filename, string $filepath, bool $removeFile = true): ?string;

    /**
     * Handles image upload from an url and returns the generated path.
     *
     * If the filename is not provided, it will extract it from image url.
     *
     * @param string      $url
     * @param string|null $filename
     *
     * @return null|string
     */
    public function uploadFromUrl(string $url, ?string $filename = null): ?string;

    /**
     * Deletes the image with the provided path.
     *
     * @param string $path
     * @param bool   $throwException
     */
    public function delete(string $path, bool $throwException = false): void;

    /**
     * @param string $path
     * @param string $newName
     *
     * @return null|string
     */
    public function duplicate(string $path, string $newName): ?string;
}
