<?php

namespace BackBeeCloud\Utils;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface YamlReaderInterface
{
    /**
     * Tries to read Yaml at the provided path and returns the content.
     *
     * @param  string $path
     *
     * @return string|array
     */
    public function read($path);
}
