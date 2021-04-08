<?php

namespace BackBee;

use BackBee\Bundle\AbstractBundle;
use BackBee\Config\Config;
use BackBeeCloud\Translation\HasTranslatableResourceInterface;
use Exception;
use function dirname;

/**
 * Class EntryPoint
 *
 * @package BackBeeCloud
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class EntryPoint extends AbstractBundle implements HasTranslatableResourceInterface
{
    /**
     * On load events.
     *
     * @param BBApplication $app
     * @param Config        $config
     *
     * @throws Exception
     */
    public static function onLoadEvents(BBApplication $app, Config $config): void
    {
        $app->getConfig()->setSection(
            'events',
            $config->getRawSection('events'),
            true
        );

        $app->getEventDispatcher()->clearAllListeners();
        $app->getEventDispatcher()->addListeners($config->getRawSection('events'));
    }

    /**
     * On load configurations.
     *
     * @param BBApplication $app
     * @param Config        $config
     *
     * @throws Exception
     */
    public static function onLoadConfigurations(BBApplication $app, Config $config): void
    {
        // Sitemaps
        $app->getConfig()->setSection(
            'sitemaps',
            array_replace_recursive(
                $config->getRawSection('sitemaps') ?? [],
                $app->getConfig()->getSection('sitemaps') ?? []
            )
        );

        // Knowledge Graph
        $app->getConfig()->setSection(
            'knowledge_graph',
            array_replace_recursive(
                $config->getRawSection('knowledge_graph') ?? [],
                $app->getConfig()->getSection('knowledge_graph') ?? []
            )
        );

        // Optimize image
        $app->getConfig()->setSection(
            'optimize_image',
            array_replace_recursive(
                $config->getRawSection('optimize_image') ?? [],
                $app->getConfig()->getSection('optimize_image') ?? []
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return $this;
    }

    /**
     * Get translation directory.
     *
     * @return string
     */
    public function getTranslationDirectory(): string
    {
        return dirname($this->getBaseDirectory()) . '/res/translations';
    }
}
