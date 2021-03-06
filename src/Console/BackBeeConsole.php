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

namespace BackBee\Console;

use App\Application;
use App\Helper\StandaloneHelper;
use BackBee\BBApplication;
use BackBee\Command\AbstractCommand;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
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
        $configFilepath = StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'config.yml';
        if (StandaloneHelper::configDir() && is_readable($configFilepath)) {
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
            $this->addBundlesCommand();
            $this->addCoreCommand();
            $this->addAppCommand();
            $this->commandsRegistered = true;
        }

        return parent::doRun($input, $output);
    }

    /**
     * Inject add command.
     *
     * @return void
     */
    private function addAppCommand(): void
    {
        try {
            if ($this->bbApplication && is_dir($dir = $this->bbApplication->getAppDir() . '/Command')) {
                $this->addCommand(
                    $dir,
                    'App'
                );
            }
        } catch (Exception $exception) {
            $this->bbApplication->getLogging()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Inject core command.
     *
     * @return void
     */
    private function addCoreCommand(): void
    {
        try {
            if (is_dir($dir = dirname(__DIR__) . '/Command')) {
                $this->addCommand(
                    $dir,
                    'BackBee'
                );
            }
        } catch (Exception $exception) {
            $this->bbApplication->getLogging()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Inject bundles command.
     *
     * @return void
     */
    private function addBundlesCommand(): void
    {
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
                    $this->bbApplication->getLogging()->error(
                        sprintf(
                            '%s : %s :%s',
                            __CLASS__,
                            __FUNCTION__,
                            $exception->getMessage()
                        )
                    );
                }
            }
        }
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
                $this->bbApplication->getLogging()->error(
                    sprintf(
                        '%s : %s :%s',
                        __CLASS__,
                        __FUNCTION__,
                        $exception->getMessage()
                    )
                );
            }
        }
    }
}
