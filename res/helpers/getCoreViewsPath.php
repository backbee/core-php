<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class getCoreViewsPath extends AbstractHelper
{
    public function __invoke($templateName)
    {
        $app = $this->_renderer->getApplication();
        $baseDir = $app->getBundle('core')->getBaseDirectory();
        $resDir = realpath($baseDir . '/../res');

        return $resDir . '/views/' . $templateName;
    }
}
