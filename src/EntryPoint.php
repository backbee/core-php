<?php

namespace BackBeeCloud;

use BackBee\BBApplication;
use BackBee\Bundle\AbstractBundle;
use BackBee\Config\Config;
use BackBeeCloud\Translation\HasTranslatableResourceInterface;
use Exception;

/**
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
        $app->getConfig()->setSection('sitemaps', $config->getRawSection('sitemaps'), false);
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
     * {@inheritdoc}
     */
    public function getTranslationDirectory()
    {
        return dirname($this->getBaseDirectory()) . '/res/translations';
    }
}
