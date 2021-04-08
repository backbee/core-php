<?php

namespace BackBee\Renderer\Helper;

use BackBee\HttpClient\UserAgent;
use BackBee\Renderer\AbstractRenderer;

/**
 * Class userAgentHelper
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class userAgentHelper extends AbstractHelper
{
    /**
     * @var UserAgent
     */
    protected $userAgentHelper;

    /**
     * userAgentHelper constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer)
    {
        $this->userAgentHelper = new UserAgent();

        parent::__construct($renderer);
    }

    /**
     * @return UserAgent
     */
    public function __invoke()
    {
        return $this->userAgentHelper;
    }
}
