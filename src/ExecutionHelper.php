<?php

namespace BackBeeCloud;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class ExecutionHelper
{
    /**
     * Converts and formats the provided size. It must be in byte unit.
     *
     * @param  int $size
     * @return string
     */
    public function formatByte($size)
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }
}
