<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

namespace BackBee\Api\Controller;

use App\Helper\StandaloneHelper;
use BackBee\BBApplication;
use BackBee\DependencyInjection\ContainerInterface;
use BackBeeCloud\Api\Controller\AbstractController;
use BackBeeCloud\Security\UserRightConstants;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class AboutController
 *
 * @package BackBee\Api\Controller
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class AboutController extends AbstractController
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * AboutController constructor.
     *
     * @param BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        parent::__construct($application);
        $this->container = $application->getContainer();
    }

    /**
     * Get information.
     *
     * @return JsonResponse
     */
    public function getInformation(): JsonResponse
    {
        $packages = [];
        $installedFile = StandaloneHelper::rootDir() . DIRECTORY_SEPARATOR .
            'vendor' . DIRECTORY_SEPARATOR .
            'composer' . DIRECTORY_SEPARATOR .
            'installed.json';

        if (is_file($installedFile)) {
            $values = json_decode(file_get_contents($installedFile), true);
            foreach ($values['packages'] as $package) {
                if (strncmp($package['name'], 'backbee', 7) === 0) {
                    $packages[] = [
                        'description' => $package['name'] . (
                            $package['description'] ? ' ' . '(' . $package['description'] . ')' : ''
                        ),
                        'version' => $package['version'],
                    ];
                }
            }
        }

        $aboutParams = $this->container->getParameter('about');

        $data = [
            'information' => [
                'backbee_version' => BBApplication::VERSION,
                'php_version' => PHP_VERSION,
                'author' => '<a href="' . $aboutParams['author_link'] . '">' . $aboutParams['author'] . '</a>',
                'licence' => '<a href="' . $aboutParams['licence_link'] . '" target="_blank">' .
                    $aboutParams['licence'] . '</a>',
            ],
        ];

        if (
            $this->securityContext->isGranted(
                UserRightConstants::CHECK_IDENTITY_ATTRIBUTE,
                UserRightConstants::SUPER_ADMIN_ID
            )
        ) {
            $data = array_merge($data, [
                'packages' => $packages,
                'bundles' => array_keys($this->container->getParameter('bundles')),
            ]);
        }

        return new JsonResponse($data);
    }
}
