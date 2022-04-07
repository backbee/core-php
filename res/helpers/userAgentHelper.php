<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

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
