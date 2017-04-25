<?php

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
