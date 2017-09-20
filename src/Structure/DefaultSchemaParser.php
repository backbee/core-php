<?php

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
