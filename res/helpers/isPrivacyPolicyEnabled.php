<?php

namespace BackBee\Renderer\Helper;

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
        return $this->getRenderer()->getApplication()->getAppParameter('privacy_policy');
    }
}
