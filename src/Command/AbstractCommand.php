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

namespace BackBee\Command;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\DependencyInjection\ContainerInterface;
use BackBeePlanet\Job\JobInterface;
use App\Helper\StandaloneHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;
use function call_user_func_array;

/**
 * Class AbstractCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class AbstractCommand extends Command
{
    /**
     * @var BBApplication
     */
    private $bbApp;

    /**
     * @var null|JobInterface
     */
    private $job;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var InputInterface
     */
    protected static $input;

    /**
     * @var OutputInterface
     */
    protected static $output;

    /**
     * @var Command
     */
    protected static $command;

    /**
     * Set BBApplication.
     *
     * @param BBApplication|null $bbApp
     */
    public function setBBApp(?BBApplication $bbApp): void
    {
        $this->bbApp = $bbApp;
    }

    /**
     * Get BBApplication.
     *
     * @return BBApplication
     */
    protected function getBBApp(): BBApplication
    {
        return $this->bbApp;
    }

    /**
     * Set job.
     *
     * @param JobInterface|null $job
     */
    public function setJob(?JobInterface $job): void
    {
        $this->job = $job;
    }

    /**
     * Get job.
     *
     * @return JobInterface|null
     */
    protected function getJob(): ?JobInterface
    {
        return $this->job;
    }

    /**
     * Get container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected function getContainer(): \Symfony\Component\DependencyInjection\ContainerInterface
    {
        return $this->bbApp->getContainer();
    }

    /**
     * Get entity manager.
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->bbApp->getEntityManager();
    }

    /**
     * Get logger.
     *
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->bbApp->getLogging();
    }

    /**
     * Get config.
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->bbApp->getConfig();
    }

    /**
     * Cleanup.
     */
    protected function cleanup(): void
    {
        exec(sprintf('rm -rf %s/*', StandaloneHelper::cacheDir()));
        exec(sprintf('chmod -R 777 %s/*', StandaloneHelper::logDir()));
    }

    /**
     * Clean memory usage.
     */
    protected function cleanMemoryUsage(): void
    {
        $this->getEntityManager()->clear();
        gc_collect_cycles();
        gc_disable();
        gc_enable();
    }

    /**
     * Get pretty memory usage.
     *
     * @return string
     */
    protected function getPrettyMemoryUsage(): string
    {
        $size = memory_get_usage();
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return @round($size / (1024 ** ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[(int) $i];
    }

    /**
     * @param Command $command
     */
    protected static function setCommand(Command $command): void
    {
        self::$command = $command;
    }

    /**
     * @return InputInterface
     */
    public static function getInput(): InputInterface
    {
        return self::$input;
    }

    /**
     * @param InputInterface $input
     */
    public static function setInput(InputInterface $input): void
    {
        self::$input = $input;
    }

    /**
     * @return OutputInterface
     */
    public static function getOutput(): OutputInterface
    {
        return self::$output;
    }

    /**
     * @param OutputInterface $output
     */
    public static function setOutput(OutputInterface $output): void
    {
        self::$output = $output;
    }

    /**
     * Parse yaml.
     *
     * @param string         $filename
     * @param array|string[] $srcCb
     *
     * @return mixed
     */
    public static function parseYaml(string $filename, array $srcCb = InstallCommand::CONFIG_DIST_YAML)
    {
        return Yaml::parse(file_get_contents(call_user_func_array($srcCb, []) . DIRECTORY_SEPARATOR . $filename));
    }

    /**
     * Ask for.
     *
     * @param string $message
     * @param bool   $isHidden
     * @param string $messageValidator
     * @param null   $defaultValue
     *
     * @return bool|mixed|string|null
     */
    public static function askFor(
        string $message,
        bool $isHidden = false,
        $defaultValue = null,
        string $messageValidator = 'Value is required'
    ) {
        $helper = self::$command->getHelper('question');
        $question = new Question($message, $defaultValue);
        $question->setValidator(function ($answer) use ($messageValidator) {
            if (empty($answer)) {
                throw new RuntimeException(
                    $messageValidator
                );
            }

            return $answer;
        });

        if ($isHidden) {
            $question->setHidden($isHidden);
        }

        return $helper->ask(self::$input, self::$output, $question);
    }
}
