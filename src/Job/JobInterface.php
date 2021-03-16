<?php

namespace BackBeePlanet\Job;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
interface JobInterface extends \Serializable
{
    /**
     * Returns identifier of the site concerned by current job.
     *
     * @return string
     */
    public function siteId();
}
