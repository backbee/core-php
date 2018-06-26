<?php

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
