<?php

namespace BackBeeCloud\Utils;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface YamlWriterInterface
{
    /**
     * Tries to write the provided content at the provided path in Yaml format.
     *
     * @param string $path
     * @param mixed  $content
     * @param bool   $override
     */
    public function write($path, $content, $override = true);
}
