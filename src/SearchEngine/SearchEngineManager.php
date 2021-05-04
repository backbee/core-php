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
