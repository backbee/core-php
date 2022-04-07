<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\Renderer;
use Symfony\Component\Translation\Translator;

/**
 * Class trans
 *
 * @package BackBee\Renderer\Helper
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class trans extends AbstractHelper
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var string|null
     */
    protected $currentLang;

    /**
     * trans constructor.
     *
     * @param Renderer $renderer
     */
    public function __construct(Renderer $renderer)
    {
        $this->setRenderer($renderer);

        $this->translator = $renderer->getApplication()->getContainer()->get('translator');
        $this->currentLang = $renderer
                ->getApplication()
                ->getContainer()
                ->get('multilang_manager')
                ->getCurrentLang() ?? 'fr';

        parent::__construct($renderer);
    }

    /**
     * Invoke.
     *
     * @param       $id
     * @param array $parameters
     * @param null  $locale
     *
     * @return string
     */
    public function __invoke($id, array $parameters = [], $locale = null): string
    {
        return $this->translator->trans($id, $parameters, null, $locale ?: $this->currentLang);
    }
}
