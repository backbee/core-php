<?php

namespace BackBeeCloud\ThemeColor;

use BackBeeCloud\ThemeColor\ThemeColorInterface;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ThemeColorManager
{
    /**
     * @var array
     */
    protected $themes = [];

    /**
     * @var ThemeColorInterface
     */
    protected $defaultTheme;

    public function __construct(array $themes)
    {
        foreach ($themes as $theme) {
            $this->add($theme);
        }

        $this->defaultTheme = array_values($this->themes)[0];
    }

    public function getDefault()
    {
        return $this->defaultTheme;
    }

    public function all()
    {
        return array_values($this->themes);
    }

    public function add(ThemeColorInterface $theme)
    {
        $this->themes[$theme->getUniqueName()] = $theme;
    }

    public function getByUniqueName($uniqueName)
    {
        if (!isset($this->themes[$uniqueName])) {
            throw new \InvalidArgumentException(sprintf('Theme does not exist: %s.', $uniqueName));
        }

        return $this->themes[$uniqueName];
    }
}
