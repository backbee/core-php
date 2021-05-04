<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

namespace BackBeePlanet\Job;

use Serializable;

/**
 * Interface JobInterface
 *
 * @package BackBeePlanet\Job
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
interface JobInterface extends Serializable
{
    /**
     * Returns identifier of the site concerned by current job.
     *
     * @return string
     */
    public function siteId(): string;
}