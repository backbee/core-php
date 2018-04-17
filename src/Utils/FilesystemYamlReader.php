<?php

namespace BackBeeCloud\Utils;

use Symfony\Component\Yaml\Yaml;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class FilesystemYamlReader implements YamlReaderInterface
{
    public function read($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf(
                'Provided path (%s) is not a file or is not readable, read aborted.',
                $path
            ));
        }

        return Yaml::parse(file_get_contents($path));
    }
}
