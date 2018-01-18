<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class amIMainUser extends AbstractHelper
{
    public function __invoke()
    {
        $app = $this->_renderer->getApplication();
        $bbtoken = $app->getBBUserToken();

        return $bbtoken
            ? $app->getContainer()->get('cloud.user_manager')->isMainUser($bbtoken->getUser())
            : false
        ;
    }
}
