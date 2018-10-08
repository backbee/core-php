<?php

namespace BackBeeCloud;

use BackBee\BBApplication;
use BackBee\Security\Token\BBUserToken;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractApplication extends BBApplication
{
    /**
     * @var string
     */
    protected static $repositoryDir;

    /**
     * Sets repository base directory.
     *
     * @param string $repositoryDir
     */
    public static function setRepositoryDir($repositoryDir)
    {
        self::$repositoryDir = $repositoryDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseRepository()
    {
        return self::$repositoryDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceBaseDir()
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'res';
    }

    public function getBaseDir()
    {
        return $this->getBaseDirectory();
    }

    /**
     * {@inheritdoc}
     */
    public function getBBUserToken()
    {
        $token = $this->getSecurityContext()->getToken();

        if (!($token instanceof BBUserToken) || $token->isExpired()) {
            $restToken = unserialize($this->getSession()->get('_security_rest_api_area'));
            $token = $restToken ?: $token;
        }

        if ($token instanceof BBUserToken && $token->isExpired()) {
            $event = new GetResponseEvent(
                $this->getController(),
                $this->getRequest(),
                HttpKernelInterface::MASTER_REQUEST
            );
            $this->getEventDispatcher()->dispatch('frontcontroller.request.logout', $event);
            $token = null;
        }

        return $token instanceof BBUserToken ? $token : null;
    }

    abstract protected function getBaseDirectory();
}
