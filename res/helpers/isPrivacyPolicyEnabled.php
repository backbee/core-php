<?php

namespace BackBee\Renderer\Helper;

use BackBeePlanet\GlobalSettings;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class isPrivacyPolicyEnabled extends AbstractHelper
{
    public function __invoke()
    {
        return (new GlobalSettings())->isPrivacyPolicyEnabled();
    }
}
