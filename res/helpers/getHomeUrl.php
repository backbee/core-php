<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getHomeUrl extends AbstractHelper
{
    public function __invoke($lang = null)
    {
        $lang = $lang ?: $this->_renderer->getApplication()->getContainer()->get('multilang_manager')->getCurrentLang();

        return null !== $lang ? sprintf('/%s/', $lang) : '/';
    }
}
