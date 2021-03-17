<?php

namespace BackBeePlanet\Standalone;

use Webmozart\Console\Config\DefaultApplicationConfig;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CommandLineConfig extends DefaultApplicationConfig
{
    /**
     * @var string
     */
    protected $rootDir;

    public function __construct($rootDir)
    {
        if (!class_exists(Application::class)) {
            echo sprintf(
                "\033[1;31mFailed to start command line. %s class must exist and extend %s\033[0m\n",
                Application::class,
                AbstractApplication::class
            );
            exit(1);
        }

        parent::__construct();

        $this->rootDir = $rootDir;
    }

    public function getRootDir()
    {
        return $this->rootDir;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $bootstrapFilepath = StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'bootstrap.yml';
        if (!is_readable($bootstrapFilepath)) {
            return;
        }

        Application::setRepositoryDir(StandaloneHelper::repositoryDir());
        $app = new Application();

        $app->getEventDispatcher()->dispatch(
            CommandLineReadyEvent::EVENT_NAME,
            new CommandLineReadyEvent($this, $app)
        );
    }
}
