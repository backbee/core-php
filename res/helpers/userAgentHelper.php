<?php

namespace BackBee\Renderer\Helper;

use BackBeeCloud\UserAgentHelper as RealUserAgentHelper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class userAgentHelper extends AbstractHelper
{
    /**
     * @var userAgentHelper
     */
    protected $userAgentHelper;

    public function __construct()
    {
        $this->userAgentHelper = new RealUserAgentHelper();
    }

    public function __invoke()
    {
        return $this->userAgentHelper;
    }
}
