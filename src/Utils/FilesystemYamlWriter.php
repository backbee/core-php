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
