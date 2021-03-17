<?php

namespace BackBeePlanet;

use BackBeePlanet\Standalone\StandaloneHelper;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GlobalSettings
 *
 * @package BackBeePlanet
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class GlobalSettings
{
    /**
     * @var array
     */
    protected $settings;

    /**
     * GlobalSettings constructor.
     */
    public function __construct()
    {
        $path = implode(DIRECTORY_SEPARATOR, [StandaloneHelper::configDir(), 'global_settings.yml']);
        $this->settings = Yaml::parse(file_get_contents($path));
    }

    /**
     * Call.
     *
     * @param $method
     * @param $parameters
     *
     * @return mixed|null
     */
    public function __call($method, $parameters)
    {
        return $this->settings[$method] ?? null;
    }

    /**
     * Is dev mode.
     *
     * @return bool
     */
    public function isDevMode(): bool
    {
        return isset($this->settings['dev_mode']) ? (bool)$this->settings['dev_mode'] : false;
    }

    /**
     * Is privacy policy enabled.
     *
     * @return bool
     */
    public function isPrivacyPolicyEnabled(): bool
    {
        return isset($this->settings['privacy_policy']['enable'])
            ? (bool)$this->settings['privacy_policy']['enable']
            : false;
    }

    /**
     * Is knowledge graph enabled.
     *
     * @return bool
     */
    public function isKnowledgeGraphEnabled(): bool
    {
        return isset($this->settings['knowledge_graph']['enable'])
            ? (bool)$this->settings['knowledge_graph']['enable']
            : true;
    }
}
