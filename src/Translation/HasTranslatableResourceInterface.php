<?php

namespace BackBeeCloud\Translation;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface HasTranslatableResourceInterface
{
    /**
     * @return string
     */
    public function getTranslationDirectory();
}
