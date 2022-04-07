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

namespace BackBeeCloud\SiteOption;

use BackBee\Bundle\Registry;
use BackBee\Config\Config;
use Doctrine\ORM\EntityManager;
use function in_array;

/**
 * Class OptionManager
 *
 * @package BackBeeCloud\SiteOption
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class OptionManager
{
    public const REGISTRY_SCOPE = 'GLOBAL';
    public const REGISTRY_TYPE = 'site_options';

    /**
     * @var array|null
     */
    protected $options;

    /**
     * @var Registry\Repository
     */
    protected $repository;

    /**
     * OptionManager constructor.
     *
     * @param EntityManager $entityManager
     * @param Config        $config
     */
    public function __construct(EntityManager $entityManager, Config $config)
    {
        $this->options = $config->getSection('site_options');
        $this->repository = $entityManager->getRepository(Registry::class);
    }

    /**
     * Is active option.
     *
     * @param $name
     *
     * @return bool
     */
    public function isActiveOption($name): bool
    {
        return in_array($name, $this->options, true)
            ? null !== $this->repository->findOneBy(
                [
                    'scope' => self::REGISTRY_SCOPE,
                    'type' => self::REGISTRY_TYPE,
                    'key' => $name,
                ]
            )
            : false;
    }
}
