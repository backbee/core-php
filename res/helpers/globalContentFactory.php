<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class globalContentFactory extends AbstractHelper
{
    public function __invoke()
    {
        return $this->_renderer->getApplication()->getContainer()->get('cloud.global_content_factory');
    }
}
