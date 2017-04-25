<?php

namespace BackBeeCloud\PageType;

use BackBee\NestedNode\Page;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractType implements TypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function layoutName()
    {
        return 1 === preg_match('~([a-z]+)$~i', static::class, $matches)
            ? str_replace('Type', 'Layout', $matches[1]) . '.twig'
            : ''
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDefault()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDuplicable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRemovable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isPullable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultContents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function exclusiveClassContents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'unique_name'   => $this->uniqueName(),
            'label'         => $this->label(),
            'is_default'    => $this->isDefault(),
            'is_protected'  => $this->isProtected(),
            'is_removable'  => $this->isRemovable(),
            'is_duplicable' => $this->isDuplicable(),
        ];
    }
}
