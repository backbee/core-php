<?php

namespace BackBeeCloud\PageType;

use BackBee\DependencyInjection\Container;
use BackBee\NestedNode\Page;
use BackBeeCloud\Entity\PageType;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TypeManager
{
    const PAGE_TYPE_TAG = 'page.type';

    protected $entyMgr;
    protected $types = [];
    protected $defaultType;

    public function __construct(Container $container)
    {
        $this->entyMgr = $container->get('em');

        foreach ($container->findTaggedServiceIds(self::PAGE_TYPE_TAG) as $id => $data) {
            $service = $container->get($id);
            $this->types[$service->uniqueName()] = $service;
            if ($service->isDefault()) {
                $this->defaultType = $service;
            }
        }
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
}
