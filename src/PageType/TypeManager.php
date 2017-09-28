<?php

namespace BackBeeCloud\PageType;

use BackBeeCloud\Entity\PageType;
use BackBeeCloud\Structure\SchemaParserInterface;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
use BackBee\DependencyInjection\Container;
use BackBee\NestedNode\Page;
use BackBee\Security\Token\BBUserToken;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TypeManager
{
    const CACHE_KEY = 'page.type_manager.cache';
    const PAGE_TYPE_TAG = 'page.type';
    const CUSTOM_CONTENTS_SCHEMA_NAME = 'custom_contents';

    protected $entyMgr;
    protected $contentMgr;
    protected $contentBuilder;
    protected $schemaParser;
    protected $types = [];
    protected $defaultType;

    public function __construct(Container $dic, SchemaParserInterface $schemaParser)
    {
        $this->entyMgr = $dic->get('em');
        $this->contentMgr = $dic->get('cloud.content_manager');
        $this->contentBuilder = $dic->get('cloud.structure.content_builder');
        $this->schemaParser = $schemaParser;

        $this->initTemplateTypes($dic);
    }

    protected function add(TypeInterface $type)
    {
        $this->types[] = $type;

        return $this;
    }

    public function all($excludeProtected = false)
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

    public function associate(Page $page, TypeInterface $type)
    {
        if (null === $association = $this->getAssociation($page)) {
            $association = new PageType($page, $type);
            $this->entyMgr->persist($association);
        }

        $association->setType($type);

        return $association;
    }

    public function find($uniqueName)
    {
        return isset($this->types[$uniqueName]) ? $this->types[$uniqueName] : null;
    }

    public function findByPage(Page $page)
    {
        $association = $this->getAssociation($page);

        return $association
            ? $association->getType()
            : ($this->defaultType ?: null)
        ;
    }

    public function getAssociation(Page $page)
    {
        $uow = $this->entyMgr->getUnitOfWork();
        if ($uow->isScheduledForInsert($page)) {
            foreach ($uow->getScheduledEntityInsertions() as $entity) {
                if ($entity instanceof PageType && $page === $entity->getPage()) {
                    return $entity;
                }
            }

            return null;
        }

        return $this->entyMgr->getRepository('BackBeeCloud\Entity\PageType')->findOneBy([
            'page' => $page,
        ]);
    }

    public function getDefaultType()
    {
        return $this->defaultType;
    }

    public function hydratePageContentsByType(TypeInterface $type, Page $page, BBUserToken $token = null)
    {
        if ($type instanceof TemplateCustomType) {
            $this->contentBuilder->hydrateContents($page, $type->contentsRawData(), $token);

            return;
        }

        $this->defaultPageContentHydratation($type, $page, $token);
    }

    protected function defaultPageContentHydratation(TypeInterface $type, Page $page, BBUserToken $token = null)
    {
        $mainContainer = $page->getContentSet()->first();
        $mainContainer->clear();
        foreach ($type->defaultContents() as $classname => $callback) {
            if (class_exists($classname)) {
                $content = new $classname();
                if (!($content instanceof AbstractClassContent)) {
                    continue;
                }

                $this->entyMgr->persist($content);
                if (is_callable($callback)) {
                    $callback($content, $page);
                }

                $colcontainer = new ColContentSet();
                $colcontainer->push($content);
                $this->entyMgr->persist($colcontainer);

                $container = new CloudContentSet();
                $container->push($colcontainer);
                $this->entyMgr->persist($container);

                $mainContainer->push($container);
            }
        }
    }

    private function initTemplateTypes(Container $dic)
    {
        $cache = $dic->get('cache.control');
        if ($result = $cache->load(self::CACHE_KEY)) {
            list(
                $this->types,
                $this->defaultType
            ) = unserialize($result);

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
        if (false == $data) {
            return;
        }

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

        $cache->save(self::CACHE_KEY, serialize([
            $this->types,
            $this->defaultType,
        ]));
    }
}
