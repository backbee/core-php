<?php

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
