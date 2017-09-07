<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class getHomeUrl extends AbstractHelper
{
    public function __invoke($lang = null)
    {
        $multilangMgr = $this->_renderer->getApplication()->getContainer()->get('multilang_manager');
        $lang = $multilangMgr->getLang($lang ?: $multilangMgr->getCurrentLang());

        return null !== $lang && $lang['is_active'] ? sprintf('/%s/', $lang['id']) : '/';
    }
}
