<?php

namespace BackBeeCloud\ClassContent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface ClassContentTransformationInterface
{
    public function apply(\ArrayObject $data);
}
