<?php

namespace BackBee\Console;

use BackBee\BBApplication;
use BackBeePlanet\Standalone\Application;
use BackBeePlanet\Standalone\StandaloneHelper;
use ReflectionClass;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use BackBee\Command\AbstractCommand;

/**
 * Class BackBeeConsole
 *
 * @package BackBeePlanet\Console
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class BackBeeConsole extends ConsoleApplication
{
    /**
     * @var BBApplication
     */
    private $bbApplication;

    /**
     * @var bool
     */
    private $commandsRegistered = false;

    /**
     * Initialize application.
     */
    public function initApplication(): void
    {
        $bootstrapFilepath = StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'bootstrap.yml';
        if (is_readable($bootstrapFilepath)) {
            Application::setRepositoryDir(StandaloneHelper::repositoryDir());
            $this->bbApplication = new Application();
        }
    }

    /**
     * Get application.
     *
     * @return null|BBApplication
     */
    public function getApplication(): ?BBApplication
    {
        return $this->bbApplication;
    }

    /**
     * {@inheritDoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->commandsRegistered) {
            foreach ($this->getApplication()->getBundles() as $bundle) {
                if (!is_dir($dir = $bundle->getBaseDirectory() . '/Command')) {
                    continue;
                }
                $finder = new Finder();
                $finder->files()->name(null === $this->bbApplication ? 'InstallCommand.php' : '*Command.php')->in($dir);
                $ns = (new ReflectionClass($bundle))->getNamespaceName() . '\\Command';
                foreach ($finder as $file) {
                    if ($relativePath = $file->getRelativePath()) {
                        $ns .= '\\' . str_replace('/', '\\', $relativePath);
                    }
                    $reflexionClass = new ReflectionClass($ns . '\\' . $file->getBasename('.php'));
                    $instance = $reflexionClass->newInstance($file->getBasename('.php'));
                    if ($reflexionClass->isSubclassOf(AbstractCommand::class)) {
                        $instance->setBBApp($this->bbApplication);
                        $this->add($instance);
                    }
                }
            }
            $this->commandsRegistered = true;
        }

        return parent::doRun($input, $output);
    }
}
