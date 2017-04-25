<?php

namespace BackBeeCloud\Structure;

use BackBee\ClassContent\AbstractClassContent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
trait ClassContentHelperTrait
{
    /**
     * Returns classname from provided type (could be classcontent type or classname).
     *
     * @param  string $type
     * @return string
     * @throws \InvalidArgumentException if provided name is not a valid classcontent type or classname
     */
    protected function getClassnameFromType($type)
    {
        $classname = null;
        if (class_exists($type)) {
            $classname = $type;
        }

        if (null === $classname) {
            $classname = AbstractClassContent::getClassnameByContentType($type);
        }

        if (!class_exists($classname)) {
            throw new \InvalidArgumentException("Provided name `{$type}` must be a classcontent type or classname.");
        }

        return $classname;
    }

    /**
     * Creates a new classcontent according to requested type and modify it to
     * be online.
     *
     * @param  string $type
     * @return AbstractClassContent
     */
    protected function createOnlineContent($type, $uid = null)
    {
        $classname = $this->getClassnameFromType($type);
        $content = new $classname($uid);
        $this->putContentOnline($content);

        return $content;
    }

    /**
     * Updates provided content to be visible in front.
     *
     * @param  AbstractClassContent $content
     * @return self
     */
    protected function putContentOnline(AbstractClassContent $content)
    {
        $content
            ->setRevision(1)
            ->setState(AbstractClassContent::STATE_NORMAL)
        ;

        return $this;
    }
}
