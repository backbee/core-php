<?php

namespace BackBeeCloud;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface ImageHandlerInterface
{
    /**
     * Uploads file located at provided filepath and returns the generated path.
     *
     * @param  string  $filename
     * @param  string  $filepath
     * @param  boolean $removeFile
     * @return null|string
     */
    public function upload($filename, $filepath, $removeFile = true);

    /**
     * Handles image upload from an url and returns the generated path.
     *
     * If the filename is not provided, it will extract it from image url.
     *
     * @param  string      $url
     * @param  string|null $filename
     * @return null|string
     */
    public function uploadFromUrl($url, $filename = null);

    /**
     * Deletes the image with the provided path.
     *
     * @param  string $path
     */
    public function delete($path, $throwException = false);
}
