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

namespace BackBeeCloud\Job;

use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeePlanet\Job\JobInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface JobHandlerInterface
{
    /**
     * Handles the provided job.
     *
     * @param JobInterface          $job
     * @param SimpleWriterInterface $writer
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer);

    /**
     * Returns true if the provided job is supported, else false.
     *
     * @param  JobInterface $job
     * @return bool
     */
    public function supports(JobInterface $job);
}
