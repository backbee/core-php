<?php

namespace BackBee\Renderer\Helper;

use BackBeePlanet\GlobalSettings;

/**
 * Class isPrivacyPolicyEnabled
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class isPrivacyPolicyEnabled extends AbstractHelper
{
    /**
     * @return bool
     */
    public function __invoke(): bool
    {
        return (new GlobalSettings())->isPrivacyPolicyEnabled();
    }
}
