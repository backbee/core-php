<?php

namespace BackBee\Renderer\Helper;

use function in_array;

/**
 * Class checkUserIP
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class checkUserIP extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @return string
     */
    public function __invoke(): string
    {
        $settings = $this->getRenderer()->getApplication()->getConfig()->getSection('whitelist');

        return (null === $settings || empty($settings)) || in_array($this->getIP(), $settings, true);
    }

    /**
     * Get IP.
     *
     * @return string
     */
    private function getIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}
