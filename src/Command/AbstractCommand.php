<?php

namespace BackBee\Command;

use BackBee\BBApplication;
use BackBee\DependencyInjection\ContainerInterface;
use BackBeePlanet\Job\JobInterface;
use BackBeePlanet\Standalone\StandaloneHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;

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
     * @return ContainerInterface
     */
    protected function getContainer(): ContainerInterface
    {
        return $this->bbApp->getContainer();
    }

    /**
     * Get entity manager.
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->getBBApp()->getEntityManager();
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
     * Cleanup.
     */
    protected function cleanup(): void
    {
        exec(sprintf('rm -rf %s/*', StandaloneHelper::cacheDir()));
        exec(sprintf('chmod -R 777 %s/*', StandaloneHelper::logDir()));
    }
}
