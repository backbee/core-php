<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBeeCloud\Translation;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TranslatorFactory
{
    public static function getTranslator(ContainerBuilder $container)
    {
        $translator = new Translator(
            $container->getParameter('bbapp.locale'),
            null,
            $container->getParameter('bbapp.cache.dir')
        );

        $translator->addLoader('yaml', new YamlFileLoader());

        $translationDirs = [];
        foreach ($container->findTaggedServiceIds('bundle') as $serviceId => $tagData) {
            if (
                $container->has($serviceId)
                && $container->get($serviceId) instanceof HasTranslatableResourceInterface
            ) {
                $translationDirs[] = $container->get($serviceId)->getTranslationDirectory();
            }
        }

        $translationDirs[] = $container->getParameter('bbapp.root.dir') . '/translations';
        foreach ($translationDirs as $directory) {
            if (is_dir($directory)) {
                $finder = new Finder();
                $finder->files()->in($directory);
                foreach ($finder as $file) {
                    if (1 === preg_match('/^messages\.([a-z]{2})\.ya?ml$/', $file->getFilename(), $matches)) {
                        $translator->addResource('yaml', $file->getPathname(), $matches[1]);
                    }
                }
            }
        }

        return $translator;
    }
}
