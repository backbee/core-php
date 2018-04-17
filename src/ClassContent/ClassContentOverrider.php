<?php

namespace BackBeeCloud\ClassContent;

use BackBeeCloud\Utils\YamlReaderInterface;
use BackBeeCloud\Utils\YamlWriterInterface;
use BackBee\BBApplication;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ClassContentOverrider
{
    const FOLDER_NAME = 'classcontents_overrider';

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

    public function __construct(
        BBApplication $app,
        array $overrideDefinitions,
        YamlReaderInterface $yamlReader,
        YamlWriterInterface $yamlWriter
    ) {
        $this->overrideDefinitions = new \SplObjectStorage();
        foreach ($overrideDefinitions as $definition) {
            $this->addDefinition($definition);
        }

        $this->app = $app;
        $this->yamlReader = $yamlReader;
        $this->yamlWriter = $yamlWriter;

        $this->initCacheDirectory($app->getCacheDir());
    }

    public function addDefinition(OverrideDefinitionInterface $definition)
    {
        $this->overrideDefinitions->attach($definition);
    }

    public function generate()
    {
        foreach ($this->overrideDefinitions as $definition) {
            $this->handleDefinition($definition);
        }

        $this->app->unshiftClassContentDir($this->cacheDirectory);
    }

    protected function handleDefinition(OverrideDefinitionInterface $definition)
    {
        $contentType = $definition->getContentType();
        $sourceYamlPath = sprintf(
            '%s/%s.yml',
            $this->getClassContentDirectoryBySourceName($definition->getSourceName()),
            $contentType
        );
        $data = $this->yamlReader->read($sourceYamlPath);

        $target = new \ArrayObject($data[$this->getNameFromContentType($contentType)]);
        foreach ($definition->getTransformations() as $transformation) {
            $transformation->apply($target);
        }

        $data[$this->getNameFromContentType($contentType)] = $target->getArrayCopy();
        $filepath = sprintf('%s/%s.yml', $this->cacheDirectory, $contentType);

        $this->mkdirOnce(dirname($filepath));
        $this->yamlWriter->write($filepath, $data);
    }

    protected function getNameFromContentType($contentType)
    {
        return basename($contentType);
    }

    protected function getClassContentDirectoryBySourceName($sourceName)
    {
        foreach ($this->app->getClassContentDir() as $directory) {
            if (false !== strpos($directory, $sourceName)) {
                return $directory;
            }
        }

        throw new \RuntimeException(sprintf(
            'Unable to find classcontent repository for the provided source name "%s".',
            $sourceName
        ));
    }

    protected function initCacheDirectory($basedir)
    {
        if (!is_dir($basedir) || !is_writable($basedir)) {
            throw new \RuntimeException(sprintf(
                'Cache base directory "%s" does not exist or is not writtable.',
                $basedir
            ));
        }

        $this->cacheDirectory = sprintf('%s/%s', $basedir, self::FOLDER_NAME);
        $this->mkdirOnce($this->cacheDirectory);
    }

    protected function mkdirOnce($path)
    {
        $umask = umask();
        umask(0);
        if (!is_dir($path) && !mkdir($path, 0777)) {
            throw new \RuntimeException("Error occurs while creating {$path}.");
        }

        umask($umask);
    }
}
