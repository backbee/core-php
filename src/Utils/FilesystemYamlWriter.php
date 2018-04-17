<?php

namespace BackBeeCloud\Utils;

use Symfony\Component\Yaml\Yaml;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class FilesystemYamlWriter implements YamlWriterInterface
{
    public function write($path, $content, $override = true)
    {
        $dirname = dirname($path);
        if (!is_dir($dirname) || !is_writable($dirname)) {
            throw new \RuntimeException(sprintf(
                'Provided directory (%s) does not exist or is not writable, write aborted.',
                $dirname
            ));
        }

        if (!$override && is_file($path)) {
            throw new \RuntimeException(sprintf(
                'Attempting to write on "%s" but the file already exists, write aborted.',
                $path
            ));
        }

        return file_put_contents($path, Yaml::dump($content, 8, 4));
    }
}
