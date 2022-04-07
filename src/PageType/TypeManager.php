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

namespace BackBeeCloud\PageType;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
use BackBee\DependencyInjection\Container;
use BackBee\NestedNode\Page;
use BackBee\Security\Token\BBUserToken;
use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Entity\PageType;
use BackBeeCloud\Structure\ContentBuilder;
use BackBeeCloud\Structure\SchemaParserInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class TypeManager
 *
 * @package BackBeeCloud\PageType
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class TypeManager
{
    public const CACHE_KEY = 'page.type_manager.cache';
    public const PAGE_TYPE_TAG = 'page.type';
    public const CUSTOM_CONTENTS_SCHEMA_NAME = 'custom_contents';

    /**
     * @var EntityManagerInterface
     */
    protected $entityMgr;

    /**
     * @var ContentManager
     */
    protected $contentMgr;

    /**
     * @var ContentBuilder
     */
    protected $contentBuilder;

    /**
     * @var SchemaParserInterface
     */
    protected $schemaParser;

    /**
     * @var array
     */
    protected $types = [];

    /**
     * @var AbstractType
     */
    protected $defaultType;

    /**
     * TypeManager constructor.
     *
     * @param Container             $dic
     * @param SchemaParserInterface $schemaParser
     */
    public function __construct(Container $dic, SchemaParserInterface $schemaParser)
    {
        $this->entityMgr = $dic->get('em');
        $this->contentMgr = $dic->get('cloud.content_manager');
        $this->contentBuilder = $dic->get('cloud.structure.content_builder');
        $this->schemaParser = $schemaParser;

        $this->initTemplateTypes($dic);
    }

    /**
     * Add type.
     *
     * @param TypeInterface $type
     *
     * @return $this
     */
    protected function add(TypeInterface $type): TypeManager
    {
        $this->types[] = $type;

        return $this;
    }

    /**
     * Get all types.
     *
     * @param bool $excludeProtected
     *
     * @return array
     */
    public function all(bool $excludeProtected = false): array
    {
        if (false === $excludeProtected) {
            return $this->types;
        }

        $types = [];
        foreach ($this->types as $type) {
            if (!$type->isProtected()) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Associate page and type.
     *
     * @param Page          $page
     * @param TypeInterface $type
     *
     * @return PageType|null
     */
    public function associate(Page $page, TypeInterface $type): ?PageType
    {
        if (null === $association = $this->getAssociation($page)) {
            $association = new PageType($page, $type);
            $this->entityMgr->persist($association);
        }

        $association->setType($type);

        return $association;
    }

    /**
     * Find type.
     *
     * @param $uniqueName
     *
     * @return mixed|null
     */
    public function find($uniqueName)
    {
        return $this->types[$uniqueName] ?? null;
    }

    /**
     * Find type by page.
     *
     * @param Page $page
     *
     * @return TypeInterface|object|string|null
     */
    public function findByPage(Page $page)
    {
        $association = $this->getAssociation($page);
        $type = $this->defaultType ?: null;

        return $association ? $association->getType() : $type;
    }

    /**
     * Get association.
     *
     * @param Page $page
     *
     * @return PageType|null
     */
    public function getAssociation(Page $page)
    {
        $uow = $this->entityMgr->getUnitOfWork();
        if ($uow->isScheduledForInsert($page)) {
            foreach ($uow->getScheduledEntityInsertions() as $entity) {
                if ($entity instanceof PageType && $page === $entity->getPage()) {
                    return $entity;
                }
            }

            return null;
        }

        return $this->entityMgr->getRepository(PageType::class)->findOneBy(['page' => $page]);
    }

    /**
     * Get default type.
     *
     * @return AbstractType
     */
    public function getDefaultType(): AbstractType
    {
        return $this->defaultType;
    }

    /**
     * Hydrate page contents by type.
     *
     * @param TypeInterface    $type
     * @param Page             $page
     * @param BBUserToken|null $token
     */
    public function hydratePageContentsByType(TypeInterface $type, Page $page, BBUserToken $token = null): void
    {
        if ($type instanceof TemplateCustomType) {
            $this->contentBuilder->hydrateContents($page, $type->contentsRawData(), $token);

            return;
        }

        $this->defaultPageContentHydratation($type, $page);
    }

    /**
     * Default page content hydratation.
     *
     * @param TypeInterface $type
     * @param Page          $page
     */
    protected function defaultPageContentHydratation(TypeInterface $type, Page $page): void
    {
        $mainContainer = $page->getContentSet()->first();
        $mainContainer->clear();
        foreach ($type->defaultContents() as $classname => $callback) {
            if (class_exists($classname)) {
                $content = new $classname();
                if (!($content instanceof AbstractClassContent)) {
                    continue;
                }

                $this->entityMgr->persist($content);
                if (is_callable($callback)) {
                    $callback($content, $page);
                }

                $colcontainer = new ColContentSet();
                $colcontainer->push($content);
                $this->entityMgr->persist($colcontainer);

                $container = new CloudContentSet();
                $container->push($colcontainer);
                $this->entityMgr->persist($container);

                $mainContainer->push($container);
            }
        }
    }

    /**
     * Init template types.
     *
     * @param Container $dic
     */
    private function initTemplateTypes(Container $dic): void
    {
        $cache = $dic->get('cache.control');
        if ($dic->isRestored() && $result = $cache->load(self::CACHE_KEY)) {
            $this->types = unserialize($result);
            foreach ($this->types as $type) {
                if ($type->isDefault()) {
                    $this->defaultType = $type;

                    break;
                }
            }

            return;
        }

        foreach ($dic->findTaggedServiceIds(self::PAGE_TYPE_TAG) as $id => $data) {
            $service = $dic->get($id);
            $this->types[$service->uniqueName()] = $service;
            if ($service->isDefault()) {
                $this->defaultType = $service;
            }
        }

        if (null === $this->schemaParser) {
            return;
        }

        $data = $this->schemaParser->getSchema(self::CUSTOM_CONTENTS_SCHEMA_NAME);

        foreach ($data['schema']['pages'] as $page) {
            if (!isset($page['custom_type'])) {
                continue;
            }

            $customType = new TemplateCustomType(
                $page['custom_type']['unique_name'],
                $page['custom_type']['label'],
                $page['contents']
            );
            $this->types[$customType->uniqueName()] = $customType;
        }

        $cache->save(self::CACHE_KEY, serialize($this->types));
    }
}
