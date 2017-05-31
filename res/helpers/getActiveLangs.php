<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getActiveLangs extends AbstractHelper
{
    public function __invoke()
    {
        $actives = [];
        foreach ($this->_renderer->getApplication()->getContainer()->get('multilang_manager')->getAllLangs() as $lang) {
            if ($lang['is_active']) {
                $actives[] = $lang;
            }
        }

        return $actives;
    }
}