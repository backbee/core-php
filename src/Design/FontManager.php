<?php

declare(strict_types=1);

namespace BackBeeCloud\Design;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class FontManager
{
    /**
     * @var array
     */
    protected $fonts;

    public function __construct()
    {
        $this->fonts = [
            [
                'label' => 'Arial',
                'value' => 'Arial, sans-serif',
            ],
            [
                'label' => 'Georgia',
                'value' => 'Georgia, serif',
            ],
            [
                'label' => 'Helvetica',
                'value' => 'Helvetica, sans-serif',
            ],
            [
                'label' => 'Time New Roman',
                'value' => 'Time New Roman, serif',
            ],
            [
                'label' => 'Trebuchet MS',
                'value' => 'Trebuchet MS, sans-serif',
            ],
            [
                'label' => 'Verdana',
                'value' => 'Verdana, sans-serif',
            ],
        ];
    }

    public function all()
    {
        return $this->fonts;
    }
}
