<?php

namespace BackBee\Renderer\Helper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class perfectTruncate extends AbstractHelper
{
    public function __invoke($text, $maxLength)
    {
        $cleaned = html_entity_decode(strip_tags($text));
        if (strlen($cleaned) < $maxLength) {
            return $cleaned;
        }

        $result = $cleaned;
        $length = $maxLength;
        if (false !== ($breakpoint = strpos($result, ' ', $maxLength))) {
            $length = $breakpoint;
        }

        $result = rtrim(substr($result, 0, $length));

        if (strlen($result) > $maxLength) {
            $pieces = explode(' ', $result);
            array_pop($pieces);
            $result = implode(' ', $pieces);
        }

        return $result . '…';
    }
}
