<?php

namespace BackBeeCloud\Structure;

use BackBee\ClassContent\AbstractClassContent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface ContentHandlerInterface
{
    /**
     * Handles hydratation of provided content with passed data.
     *
     * @param  AbstractClassContent $content
     * @param  array                $data
     */
    public function handle(AbstractClassContent $content, array $data);

    /**
     *
     * Build config probided by content
     *
     * @param AbstractClassContent $content
     * @param array                $data
     */
    public function handleReverse(AbstractClassContent $content, array $data = []);

    /**
     * Returns true if the provided content is supported by current content handler.
     *
     * @param  AbstractClassContent $content
     * @return boolean
     */
    public function supports(AbstractClassContent $content);
}
