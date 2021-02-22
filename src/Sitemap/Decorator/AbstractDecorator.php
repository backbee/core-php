<?php

namespace BackBeePlanet\Sitemap\Decorator;

use BackBee\Renderer\RendererInterface;
use BackBeePlanet\Sitemap\Query\CollectorInterface;

/**
 * Class AbstractDecorator
 *
 * Abstract rendering decorator for sitemaps collector.
 *
 * @package BackBeePlanet\Sitemap\Decorator
 */
abstract class AbstractDecorator implements DecoratorInterface
{
    /**
     * An renderer engine.
     *
     * @var RendererInterface
     */
    private $renderer;

    /**
     * The collector to be decorated.
     *
     * @var CollectorInterface
     */
    private $collector;

    /**
     * Sets the renderer engine to used.
     *
     * @param RendererInterface $renderer
     */
    public function setRenderer(RendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * Sets the data collector for this decorator.
     *
     * @param CollectorInterface $collector
     */
    public function setCollector(CollectorInterface $collector): void
    {
        $this->collector = $collector;
    }

    /**
     * Returns the current collector.
     *
     * @return CollectorInterface
     */
    public function getCollector(): CollectorInterface
    {
        return $this->collector;
    }

    /**
     * Returns the renderer engine.
     *
     * @return RendererInterface
     */
    protected function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }
}
