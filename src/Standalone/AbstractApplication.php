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

namespace BackBeePlanet\Standalone;

use BackBee\BBApplication;
use BackBee\Security\Token\BBUserToken;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class AbstractApplication
 *
 * @package BackBeePlanet\Standalone
 *
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
    public static function setRepositoryDir(string $repositoryDir): void
    {
        self::$repositoryDir = $repositoryDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseRepository(): string
    {
        return self::$repositoryDir;
    }

    /**
     * Get resource base dir.
     */
    public function getResourceBaseDir(): string
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'res';
    }

    /**
     * Get base dir.
     *
     * @return string
     */
    public function getBaseDir(): string
    {
        return $this->getBaseDirectory();
    }

    /**
     * {@inheritdoc}
     */
    public function getBBUserToken(): ?BBUserToken
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
