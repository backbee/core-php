<?php

namespace BackBee\Renderer\Helper;

use BackBee\Bundle\Registry;
use BackBee\Renderer\Helper\AbstractHelper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class sitename extends AbstractHelper
{
    public function __invoke()
    {
        $app = $this->_renderer->getApplication();
        $sitename = $app->getSite()->getLabel();
        $registry = $app->getEntityManager()->getRepository(Registry::class)->findOneBy([
            'key' => 'site_label',
            'scope' => 'GLOBAL',
        ]);
        if ($registry && false != trim($registry->getValue())) {
            $sitename = $registry->getValue();
        }

        return $sitename;
    }
}
