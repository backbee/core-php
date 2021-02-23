<?php

namespace BackBeeCloud\SearchEngine;

use BackBeeCloud\Controller\SearchEngineController;
use BackBeeCloud\UserPreference\UserPreferenceManager;

/**
 * Class SearchEngineManager
 *
 * @package BackBeeCloud\Search
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SearchEngineManager
{
    /**
     * @var UserPreferenceManager
     */
    private $userPreferenceManager;

    /**
     * SearchEngineManager constructor.
     *
     * @param UserPreferenceManager $userPreferenceManager
     */
    public function __construct(UserPreferenceManager $userPreferenceManager)
    {
        $this->userPreferenceManager = $userPreferenceManager;
    }

    /**
     * Check is search engine is activated.
     *
     * @return bool
     */
    public function googleSearchEngineIsActivated(): bool
    {
        $data = $this->userPreferenceManager->dataOf(SearchEngineController::USER_PREFERENCE_DATA_KEY);

        return $data['robots_index'] ?? false;
    }

    /**
     * get metadata robots.
     *
     * @param array $metadata
     *
     * @return string|null
     */
    public function getMetadataRobots(array $metadata): ?string
    {
        $content = [
            $metadata['index'] && $this->googleSearchEngineIsActivated() ? 'index' : 'noindex',
            $metadata['follow'] && $this->googleSearchEngineIsActivated() ? 'follow' : 'nofollow'
        ];

        return implode(', ', $content);
    }
}
