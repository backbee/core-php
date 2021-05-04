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

namespace BackBeePlanet\Sitemap\Decorator;

use BackBee\Renderer\RendererInterface;
use BackBeePlanet\Sitemap\Query\CollectorInterface;

/**
 * Interface DecoratorInterface
 *
 * Interface for sitemap decorators.
 *
 * @package BackBeePlanet\Sitemap\Decorator
 */
interface DecoratorInterface
{
    /**
     * Renders every sitemap collected.
     *
     * @param array $preset Optional, an array of preset values for discriminators.
     * @param array $params Optional rendering parameters.
     *
     * @return array        An array of rendered sitemaps indexed by their URLs.
     */
    public function render(array $preset = [], array $params = []): array;

    /**
     * Sets the renderer engine to used.
     *
     * @param RendererInterface $renderer
     */
    public function setRenderer(RendererInterface $renderer);

    /**
     * Sets the data collector for this decorator.
     *
     * @param CollectorInterface $collector
     */
    public function setCollector(CollectorInterface $collector);

    /**
     * Returns the current collector.
     *
     * @return CollectorInterface
     */
    public function getCollector(): CollectorInterface;
}
