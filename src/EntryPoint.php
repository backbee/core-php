<?php

namespace BackBeeCloud;

use BackBeeCloud\Translation\HasTranslatableResourceInterface;
use BackBee\BBApplication;
use BackBee\Bundle\AbstractBundle;
use BackBee\Config\Config;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class EntryPoint extends AbstractBundle implements HasTranslatableResourceInterface
{
    /**
     * @param  BBApplication $app
     * @param  Config        $config
     */
    public static function onLoadEvents(BBApplication $app, Config $config)
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

    public function getTranslationDirectory()
    {
        return realpath($this->getBaseDirectory() . '/../res/translations');
    }
}
