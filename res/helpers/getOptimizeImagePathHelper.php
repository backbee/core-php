<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class getOptimizeImagePathHelper extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @param string $path
     * @param bool   $inFluid
     * @param int    $colSize
     *
     * @return string
     */
    public function __invoke(string $path, bool $inFluid, int $colSize): string
    {
        return $this
            ->getRenderer()
            ->getApplication()
            ->getContainer()
            ->get('app.optimize_image.manager')
            ->getOptimizeImagePath($path, $inFluid, $colSize);
    }
}
