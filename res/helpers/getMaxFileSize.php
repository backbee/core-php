<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class getMaxFileSize extends AbstractHelper
{
    /**
     *
     * @var int
     */
    protected $maxFileSize;

    public function __construct()
    {
        $maxSize = $this->parseSize(ini_get('post_max_size'));

        $uploadMaxSize = $this->parseSize(ini_get('upload_max_size'));

        if ($uploadMaxSize > 0 && $uploadMaxSize < $maxSize) {
            $maxSize = $uploadMaxSize;
        }

        $this->maxFileSize = $maxSize;
    }

    public function __invoke()
    {
        return $this->maxFileSize;
    }

    protected function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            $size = $size * pow(1024, stripos('bkmgtpezy', $unit[0]));
        }

        return round($size);
    }
}
