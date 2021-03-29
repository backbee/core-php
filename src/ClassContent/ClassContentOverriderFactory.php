<?php

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
