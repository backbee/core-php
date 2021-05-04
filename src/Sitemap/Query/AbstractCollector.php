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

namespace BackBeePlanet\Sitemap\Query;

use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AbstractCollector
 *
 * Abstract class for sitemaps collector.
 *
 * @package BackBeePlanet\Sitemap\Query
 */
abstract class AbstractCollector implements CollectorInterface
{
    /**
     * Pattern to extract discriminators from the URL pattern.
     */
    public const DISCRIMINATOR_PATTERN = '|{([^}]+)}|';

    /**
     * The current BackBee services container.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * The pattern for generated URLs.
     *
     * @var string
     */
    private $urlPattern;

    /**
     * A set of selection limits.
     *
     * @var array
     */
    private $limits;

    /**
     * Array of accepted discriminators by this collector.
     *
     * @var string[];
     */
    protected $acceptedDiscriminators = [];

    /**
     * An array of preset values for discriminators.
     *
     * @var array
     */
    protected $preset;

    /**
     * Sets the container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * Returns the container.
     *
     * @return ContainerInterface
     *
     * @throws RuntimeException  Thrown if the container is not defined.
     */
    protected function getContainer(): ContainerInterface
    {
        if (null === $this->container) {
            throw new RuntimeException('A DI container is missing for collector');
        }

        return $this->container;
    }

    /**
     * Returns the URL pattern for this collector.
     *
     * @return string The URL pattern.
     */
    public function getUrlPattern(): string
    {
        return $this->urlPattern;
    }

    /**
     * Sets the URL pattern for this collector.
     *
     * @param string $urlPattern The URL pattern to set.
     *
     * @return AbstractCollector The current instance for chained operations.
     */
    public function setUrlPattern(string $urlPattern): AbstractCollector
    {
        $this->urlPattern = $urlPattern;

        return $this;
    }

    /**
     * Gets an array of discriminators accepted by this collector.
     *
     * @return array An array of accepted discriminators.
     */
    public function getAcceptedDiscriminators(): array
    {
        return $this->acceptedDiscriminators;
    }

    /**
     * Gets the discriminators found in the URL pattern.
     *
     * @return array An array of discriminators to be used.
     */
    protected function getDiscriminatorsFromPattern(): array
    {
        $discriminators = [];

        $match = preg_match_all(
            self::DISCRIMINATOR_PATTERN,
            $this->getUrlPattern(),
            $discriminators
        );

        if (false !== $match) {
            $discriminators = $discriminators[1] ?? [];
        }

        return array_intersect($this->getAcceptedDiscriminators(), $discriminators);
    }

    /**
     * Sets preset values for the discriminators.
     *
     * @param array $preset Associative array of preset values.
     *
     * @return AbstractCollector The current instance for chained operations.
     */
    protected function setPreset(array $preset = []): AbstractCollector
    {
        $this->preset = [];
        foreach ($this->getAcceptedDiscriminators() as $key) {
            if (isset($preset[$key])) {
                $this->preset[$key] = $preset[$key];
            }
        }

        return $this;
    }

    /**
     * Sets the selection limits.
     *
     * @param array $limits An array of limits.
     *
     * @return AbstractCollector The current instance for chained operations.
     */
    public function setLimits(array $limits): AbstractCollector
    {
        $this->limits = $limits;

        return $this;
    }

    /**
     * Returns the selection limits.
     *
     * @return array
     */
    protected function getLimits(): array
    {
        return $this->limits;
    }

    /**
     * Returns the limit identified by $name.
     *
     * @param string $name The name of the limit.
     *
     * @return mixed|null The limit if exists, null otherwise.
     */
    protected function getLimit(string $name)
    {
        $limits = $this->getLimits();

        return $limits[$name] ?? null;
    }
}
