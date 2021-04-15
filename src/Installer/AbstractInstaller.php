<?php

namespace BackBee\Installer;

use BackBee\ApplicationInterface;
use BackBee\BBApplication;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class AbstractInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class AbstractInstaller
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ApplicationInterface
     */
    private $application;

    /**
     * AbstractInstaller constructor.
     *
     * @param BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        $this->entityManager = $application->getEntityManager();
        $this->application = $application;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @return ApplicationInterface
     */
    public function getApplication(): ApplicationInterface
    {
        return $this->application;
    }
}