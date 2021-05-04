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

namespace BackBeeCloud\Structure;

use Symfony\Component\Yaml\Yaml;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class DefaultSchemaParser implements SchemaParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function getSchema($name)
    {
        if (1 !== preg_match('~_schema$~', $name)) {
            $name = $name . '_schema';
        }

        $path = realpath(sprintf('%s/%s.yml', $this->basedir(), $name));
        if (false === $path) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot find structure schema for "%s".',
                $name
            ));
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot read the file located at "%s".',
                $path
            ));
        }

        return Yaml::parse(file_get_contents($path));
    }

    /**
     * Returns structures base directory.
     *
     * @return string
     */
    protected function basedir()
    {
        return realpath(__DIR__ . '/../../res/structures');
    }
}
