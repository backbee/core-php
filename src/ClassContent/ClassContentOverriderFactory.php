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

namespace BackBeeCloud\ClassContent;

use BackBee\BBApplication;
use BackBeeCloud\Utils\FilesystemYamlReader;
use BackBeeCloud\Utils\FilesystemYamlWriter;
use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ClassContentOverriderFactory
 *
 * @package BackBeeCloud\ClassContent
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ClassContentOverriderFactory
{
    public const CLASSCONTENT_OVERRIDE_DEFINITION_TAG = 'classcontent_overrider.definition';

    /**
     * Create class content overrider.
     *
     * @param ContainerBuilder $container
     * @param BBApplication    $app
     *
     * @return ClassContentOverrider
     * @throws Exception
     */
    public static function createClassContentOverrider(ContainerBuilder $container, BBApplication $app)
    {
        $definitions = [];
        foreach ($container->findTaggedServiceIds(self::CLASSCONTENT_OVERRIDE_DEFINITION_TAG) as $id => $tagData) {
            if ($container->has($id)) {
                $definitions[] = $container->get($id);
            }
        }

        return new ClassContentOverrider(
            $app,
            $definitions,
            new FilesystemYamlReader(),
            new FilesystemYamlWriter()
        );
    }
}
