<?php

namespace BackBeeCloud\ClassContent;

use BackBeeCloud\Utils\FilesystemYamlReader;
use BackBeeCloud\Utils\FilesystemYamlWriter;
use BackBee\BBApplication;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ClassContentOverriderFactory
{
    const CLASSCONTENT_OVERRIDE_DEFINITION_TAG = 'classcontent_overrider.definition';

    public function createClassContentOverrider(ContainerBuilder $container, BBApplication $app)
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
