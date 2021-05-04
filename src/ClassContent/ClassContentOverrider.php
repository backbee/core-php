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

use ArrayObject;
use BackBee\BBApplication;
use BackBeeCloud\Utils\YamlReaderInterface;
use BackBeeCloud\Utils\YamlWriterInterface;
use Exception;
use RuntimeException;
use SplObjectStorage;

/**
 * Class ClassContentOverrider
 *
 * @package BackBeeCloud\ClassContent
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ClassContentOverrider
{
    public const FOLDER_NAME = 'classcontents_overrider';

    /**
     * @var BBApplication
     */
    private $app;

    /**
     * @var OverrideDefinitionInterface
     */
    private $overrideDefinitions;

    /**
     * @var YamlReaderInterface
     */
    private $yamlReader;

    /**
     * @var YamlWriterInterface
     */
    private $yamlWriter;

    /**
     * @var string
     */
    private $cacheDirectory;

    /**
     * ClassContentOverrider constructor.
     *
     * @param BBApplication       $app
     * @param array               $overrideDefinitions
     * @param YamlReaderInterface $yamlReader
     * @param YamlWriterInterface $yamlWriter
     *
     * @throws Exception
     */
    public function __construct(
        BBApplication $app,
        array $overrideDefinitions,
        YamlReaderInterface $yamlReader,
        YamlWriterInterface $yamlWriter
    ) {
        $this->overrideDefinitions = new SplObjectStorage();
        foreach ($overrideDefinitions as $definition) {
            $this->addDefinition($definition);
        }

        $this->app = $app;
        $this->yamlReader = $yamlReader;
        $this->yamlWriter = $yamlWriter;

        $this->initCacheDirectory($app->getCacheDir());
    }

    /**
     * Add definition.
     *
     * @param OverrideDefinitionInterface $definition
     */
    public function addDefinition(OverrideDefinitionInterface $definition): void
    {
        $this->overrideDefinitions->attach($definition);
    }

    /**
     * Generate.
     */
    public function generate(): void
    {
        foreach ($this->overrideDefinitions as $definition) {
            $this->handleDefinition($definition);
        }

        $this->app->unshiftClassContentDir($this->cacheDirectory);
    }

    /**
     * Handle definition.
     *
     * @param OverrideDefinitionInterface $definition
     */
    protected function handleDefinition(OverrideDefinitionInterface $definition): void
    {
        $contentType = $definition->getContentType();
        $sourceYamlPath = sprintf(
            '%s/%s.yml',
            $this->getClassContentDirectoryBySourceName($definition->getSourceName()),
            $contentType
        );
        $data = $this->yamlReader->read($sourceYamlPath);

        $target = new ArrayObject($data[$this->getNameFromContentType($contentType)]);
        foreach ($definition->getTransformations() as $transformation) {
            $transformation->apply($target);
        }

        $data[$this->getNameFromContentType($contentType)] = $target->getArrayCopy();
        $filepath = sprintf('%s/%s.yml', $this->cacheDirectory, $contentType);

        $this->mkdirOnce(dirname($filepath));
        $this->yamlWriter->write($filepath, $data);
    }

    /**
     * Get name from content type.
     *
     * @param $contentType
     *
     * @return string
     */
    protected function getNameFromContentType($contentType): string
    {
        return basename($contentType);
    }

    /**
     * Get class content directory by source name.
     *
     * @param $sourceName
     *
     * @return string
     */
    protected function getClassContentDirectoryBySourceName($sourceName): string
    {
        foreach ($this->app->getClassContentDir() as $directory) {
            if (false !== strpos($directory, $sourceName)) {
                return $directory;
            }
        }

        return dirname($this->app->getBundle('core')->getBaseDirectory()) . '/res/ClassContent';
    }

    /**
     * Init cache directory.
     *
     * @param $basedir
     */
    protected function initCacheDirectory($basedir): void
    {
        if (!is_dir($basedir) || !is_writable($basedir)) {
            throw new RuntimeException(
                sprintf(
                    'Cache base directory "%s" does not exist or is not writtable.',
                    $basedir
                )
            );
        }

        $this->cacheDirectory = sprintf('%s/%s', $basedir, self::FOLDER_NAME);
        $this->mkdirOnce($this->cacheDirectory);
    }

    /**
     * Create folder.
     *
     * @param $path
     */
    protected function mkdirOnce($path): void
    {
        $umask = umask();
        umask(0);
        if (!is_dir($path) && !mkdir($path, 0777)) {
            throw new RuntimeException("Error occurs while creating {$path}.");
        }

        umask($umask);
    }
}
