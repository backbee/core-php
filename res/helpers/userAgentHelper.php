<?php

namespace BackBee\Renderer\Helper;

use BackBeeCloud\UserAgentHelper as RealUserAgentHelper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class userAgentHelper extends AbstractHelper
{
    public function __invoke()
    {
        return new RealUserAgentHelper();
    }
}
