<?php

namespace BackBee\Console;

use BackBee\BBApplication;
use BackBeePlanet\Standalone\Application;
use BackBeePlanet\Standalone\StandaloneHelper;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use BackBee\Command\AbstractCommand;
use function dirname;

/**
 * Class BackBeeConsole
 *
 * @package BackBee\Console
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
        if (StandaloneHelper::configDir() && is_readable($bootstrapFilepath)) {
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
            if ($this->bbApplication) {
                foreach ($this->bbApplication->getBundles() as $bundle) {
                    if (!is_dir($dir = $bundle->getBaseDirectory() . '/Command')) {
                        continue;
                    }
                    try {
                        $this->addCommand(
                            $dir,
                            (new ReflectionClass($bundle))->getNamespaceName()
                        );
                    } catch (Exception $exception) {
                    }
                }
            } elseif (is_dir($dir = dirname(__DIR__) . '/Command')) {
                $this->addCommand(
                    $dir,
                    'BackBee'
                );
            }
            $this->commandsRegistered = true;
        }

        return parent::doRun($input, $output);
    }

    /**
     * Add command.
     *
     * @param string $dir
     * @param string $namespaceName
     */
    private function addCommand(string $dir, string $namespaceName): void
    {
        $finder = new Finder();
        $finder->files()->name($this->bbApplication === null ? 'InstallCommand.php' : '*Command.php')->in($dir);
        $ns = $namespaceName . '\\Command';
        foreach ($finder as $file) {
            if ($relativePath = $file->getRelativePath()) {
                $ns .= '\\' . str_replace('/', '\\', $relativePath);
            }
            try {
                $reflexionClass = new ReflectionClass($ns . '\\' . $file->getBasename('.php'));
                if ($reflexionClass->isSubclassOf(AbstractCommand::class)) {
                    $instance = $reflexionClass->newInstance($file->getBasename('.php'));
                    $instance->setBBApp($this->bbApplication);
                    $this->add($instance);
                }
            } catch (Exception $exception) {
            }
        }
    }
}
