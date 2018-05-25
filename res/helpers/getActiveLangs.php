<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getActiveLangs extends AbstractHelper
{
    public function __invoke()
    {
        return $this->_renderer->getApplication()->getContainer()->get('multilang_manager')->getActiveLangs();
    }
}
