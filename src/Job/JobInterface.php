<?php

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
