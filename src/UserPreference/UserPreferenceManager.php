<?php

namespace BackBeeCloud\UserPreference;

use BackBee\Bundle\Registry;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class UserPreferenceManager
{
    const REGISTRY_SCOPE = 'USER_PREFERENCES';

    /**
     * @var EntityManager
     */
    protected $entyMgr;

    public function __construct(EntityManager $entyMgr)
    {
        $this->entyMgr = $entyMgr;
    }

    /**
     * Returns an array that contains all user preferences.
     *
     * @return array
     */
    public function all()
    {
        $result = [];
        $all = $this->entyMgr->getRepository(Registry::class)->findBy([
            'scope' => self::REGISTRY_SCOPE,
        ]);
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
    public function setDataOf($name, array $data)
    {
        foreach ($data as $key => $value) {
            $this->addInto($name, $key, $value);
        }
    }

    /**
     * Removes data associated to the given name.
     *
     * @param  string $name
     */
    public function removeDataOf($name)
    {
        $rawData = $this->entyMgr->getRepository(Registry::class)->findBy([
            'scope' => self::REGISTRY_SCOPE,
            'type'  => $name,
        ]);
        foreach ($rawData as $row) {
            $this->entyMgr->remove($row);
        }

        $this->entyMgr->flush();
    }

    /**
     * Adds provided key and valud into provided name key in user preference.
     *
     * @param string $name
     * @param string $key
     * @param string $value
     */
    public function addInto($name, $key, $value)
    {
        $this->isAuthorizedNameAndKey($name, $key, $value);
        $registry = $this->entyMgr->getRepository(Registry::class)->findOneBy([
            'scope' => self::REGISTRY_SCOPE,
            'type'  => $name,
            'key'   => $key,
        ]);
        if (null === $registry) {
            $registry = new Registry();
            $registry->setScope(self::REGISTRY_SCOPE);
            $registry->setType($name);
            $registry->setKey($key);

            $this->entyMgr->persist($registry);
        }

        $registry->setValue($value);
        $this->entyMgr->flush($registry);
    }

    /**
     * Searches data of requested key name inside user preferences and returns it.
     *
     * Notes that if the key name does not exist, it returns an empty array.
     *
     * @param  string $name
     * @return array
     */
    public function dataOf($name)
    {
        $result = [];
        $rawData = $this->entyMgr->getRepository(Registry::class)->findBy([
            'scope' => self::REGISTRY_SCOPE,
            'type'  => $name,
        ]);
        foreach ($rawData as $row) {
            $result[$row->getKey()] = $row->getValue();
        }

        return $result;
    }

    /**
     * Searches for a specific value of provided key name in user preferences.
     *
     * Notes that it returns null if the resquested value does not exist.
     *
     * @param  string $name
     * @param  string $key
     * @return string|null
     */
    public function singleDataOf($name, $key)
    {
        $data = $this->getDataOf($name);

        return isset($data[$key]) ? $data[$key] : null;
    }

    protected function isAuthorizedNameAndKey($name, $key = null, $value = null)
    {
        $authorizedKeys = $this->authorizedNamesAndKeys();
        $target = isset($authorizedKeys[$name]) ? $authorizedKeys[$name] : null;
        if (null === $target) {
            throw new \InvalidArgumentException(sprintf(
                '[%s] %s is not authorized as user preference name',
                __METHOD__,
                $name
            ));
        }

        if (null === $key) {
            return true;
        }

        if (!in_array($key, array_keys($target))) {
            throw new \InvalidArgumentException(sprintf(
                '[%s] %s is not authorized as user preference %s keyname',
                __METHOD__,
                $key,
                $name
            ));
        }

        if (null === $value) {
            return true;
        }

        $callback = $target[$key];
        if (!is_callable($callback)) {
            return true;
        }

        $result = (bool) $callback($value);
        if (!$result) {
            throw new \InvalidArgumentException(sprintf(
                '[%s] provided value is not valid for user preferences %s %s',
                __METHOD__,
                $name,
                $key
            ));
        }

        return true;
    }

    protected function authorizedNamesAndKeys()
    {
        return [
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
                'url_16x16'   => 'is_string',
                'url_32x32'   => 'is_string',
                'url_144x144' => 'is_string',
                'url_152x152' => 'is_string',
            ],
            'google-analytics' => [
                'code' => function ($code) {
                    return 1 === preg_match('#^UA\-[0-9]+\-[0-9]+$#', $code);
                },
            ],
            'facebook-analytics' => [
                'code' => function ($code) {
                    return 1 === preg_match('#^[0-9]{15}$#', $code);
                },
            ]
        ];
    }
}
