<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBeeCloud\UserPreference;

use BackBee\Bundle\Registry;
use BackBeeCloud\MultiLang\MultiLangManager;
use Doctrine\ORM\EntityManager;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use function array_key_exists;
use function is_array;
use function is_callable;

/**
 * Class UserPreferenceManager
 *
 * @package BackBeeCloud\UserPreference
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class UserPreferenceManager
{
    public const REGISTRY_SCOPE = 'USER_PREFERENCES';

    /**
     * @var EntityManager
     */
    protected $entityMgr;

    /**
     * @var MultiLangManager
     */
    protected $multiLangManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * UserPreferenceManager constructor.
     *
     * @param EntityManager            $entityMgr
     * @param MultiLangManager         $multiLangManager
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(EntityManager $entityMgr, MultiLangManager $multiLangManager, LoggerInterface $logger)
    {
        $this->entityMgr = $entityMgr;
        $this->multiLangManager = $multiLangManager;
        $this->logger = $logger;
    }

    /**
     * Returns an array that contains all user preferences.
     *
     * @return array
     */
    public function all(): array
    {
        $result = [];
        $all = $this->entityMgr->getRepository(Registry::class)->findBy(
            [
                'scope' => self::REGISTRY_SCOPE,
            ]
        );
        foreach ($all as $row) {
            if (!isset($result[$row->getType()])) {
                $result[$row->getType()] = [];
            }

            $result[$row->getType()][$row->getKey()] = $row->getValue();
        }

        return $result;
    }

    /**
     * Adds the provided array of data under provided name key in user preference.
     *
     * @param string $name
     * @param array  $data
     */
    public function setDataOf(string $name, array $data): void
    {
        foreach ($data as $key => $value) {
            $this->addInto($name, $key, is_array($value) ? json_encode($value) : $value);
        }
    }

    /**
     * Removes data associated to the given name.
     *
     * @param string $name
     */
    public function removeDataOf(string $name): void
    {
        try {
            $rawData = $this->entityMgr->getRepository(Registry::class)->findBy(
                [
                    'scope' => self::REGISTRY_SCOPE,
                    'type' => $name,
                ]
            );
            foreach ($rawData as $row) {
                $this->entityMgr->remove($row);
            }

            $this->entityMgr->flush();
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Adds provided key and value into provided name key in user preference.
     *
     * @param string $name
     * @param string $key
     * @param string $value
     */
    public function addInto(string $name, string $key, string $value): void
    {
        $this->isAuthorizedNameAndKey($name, $key, $value);

        $registry = $this->entityMgr->getRepository(Registry::class)->findOneBy(
            [
                'scope' => self::REGISTRY_SCOPE,
                'type' => $name,
                'key' => $key,
            ]
        );

        try {
            if (null === $registry) {
                $registry = new Registry();
                $registry->setScope(self::REGISTRY_SCOPE);
                $registry->setType($name);
                $registry->setKey($key);

                $this->entityMgr->persist($registry);
            }

            $registry->setValue($value);
            $this->entityMgr->flush($registry);
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Searches data of requested key name inside user preferences and returns it.
     *
     * Notes that if the key name does not exist, it returns an empty array.
     *
     * @param string $name
     *
     * @return array
     */
    public function dataOf(string $name): array
    {
        $result = [];
        $rawData = $this->entityMgr->getRepository(Registry::class)->findBy(
            [
                'scope' => self::REGISTRY_SCOPE,
                'type' => $name,
            ]
        );
        foreach ($rawData as $row) {
            $result[$row->getKey()] = $row->getValue();
        }

        return $result;
    }

    /**
     * Searches for a specific value of provided key name in user preferences.
     *
     * Notes that it returns null if the requested value does not exist.
     *
     * @param string $name
     * @param string $key
     *
     * @return string|null
     */
    public function singleDataOf(string $name, string $key): ?string
    {
        $data = $this->dataOf($name);

        return $data[$key] ?? null;
    }

    /**
     * Check if is authorized name and key.
     *
     * @param      $name
     * @param null $key
     * @param null $value
     *
     * @return bool
     */
    protected function isAuthorizedNameAndKey($name, $key = null, $value = null)
    {
        $authorizedKeys = $this->authorizedNamesAndKeys();
        $target = $authorizedKeys[$name] ?? null;
        if (null === $target) {
            throw new InvalidArgumentException(
                sprintf(
                    '[%s] %s is not authorized as user preference name',
                    __METHOD__,
                    $name
                )
            );
        }

        if (null === $key) {
            return true;
        }

        if (!array_key_exists($key, $target)) {
            throw new InvalidArgumentException(
                sprintf(
                    '[%s] %s is not authorized as user preference %s keyname',
                    __METHOD__,
                    $key,
                    $name
                )
            );
        }

        if (null === $value) {
            return true;
        }

        $callback = $target[$key];
        if (!is_callable($callback)) {
            return true;
        }

        $result = (bool)$callback($value);
        if (!$result) {
            throw new InvalidArgumentException(
                sprintf(
                    '[%s] provided value is not valid for user preferences %s %s',
                    __METHOD__,
                    $name,
                    $key
                )
            );
        }

        return true;
    }

    /**
     * Return array with authorized names and keys.
     *
     * @return array
     */
    protected function authorizedNamesAndKeys(): array
    {
        $result = [
            'error_page_404' => [
                'button_title' => 'is_string',
                'description' => 'is_string',
                'title' => 'is_string',
            ],
            'error_page_500' => [
                'button_title' => 'is_string',
                'description' => 'is_string',
                'title' => 'is_string',
            ],
            'search-engines' => [
                'robots_index' => null,
            ],
            'favicon' => [
                'url_16x16' => 'is_string',
                'url_32x32' => 'is_string',
                'url_144x144' => 'is_string',
                'url_152x152' => 'is_string',
            ],
            'google-analytics' => [
                'code' => true,
            ],
            'gtm-analytics' => [
                'code' => function ($code) {
                    return preg_match('#^GTM-[a-zA-Z0-9]+$#', $code) === 1;
                },
            ],
            'gsc-analytics' => [
                'content' => true,
            ],
            'matomo-analytics' => [
                'tracker_url' => 'is_string',
                'site_id' => 'is_string',
            ],
            'privacy-policy' => [
                'banner_message' => 'is_string',
                'learn_more_url' => 'is_string',
                'learn_more_link_title' => 'is_string',
            ],
            'knowledge-graph' => [
                'organization' => 'is_string',
                'organization_social_profiles' => 'is_string',
                'website_name' => 'is_string',
                'website_description' => 'is_string',
            ],
            'dev-mode' => [
                'enabled' => null
            ]
        ];

        foreach ($this->multiLangManager->getActiveLangs() as $lang) {
            $result['privacy-policy'][$lang['id'] . '_banner_message'] = 'is_string';
            $result['privacy-policy'][$lang['id'] . '_learn_more_url'] = 'is_string';
            $result['privacy-policy'][$lang['id'] . '_learn_more_link_title'] = 'is_string';
        }

        return $result;
    }
}
