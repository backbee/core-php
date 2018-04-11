<?php

namespace BackBeeCloud\PageType;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class HomeType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return 'Home layout';
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDuplicable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isRemovable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isPullable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return 'home';
    }
}
