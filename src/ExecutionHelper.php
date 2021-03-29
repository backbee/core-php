<?php

namespace BackBeeCloud;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ExecutionHelper
{
    /**
     * Converts and formats the provided size. It must be in byte unit.
     *
     * @param  int $size
     * @return string
     */
    public function formatByte($size): string
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }
}
