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

use BackBee\Renderer\AbstractRenderer;
use BackBeeCloud\MultiLang\MultiLangManager;

/**
 * Class getCurrentLang
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class getCurrentLang extends AbstractHelper
{
    /**
     * @var MultiLangManager
     */
    public $multiLangManager;

    /**
     * getCurrentLang constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $this->multiLangManager = $this->_renderer->getApplication()->getContainer()->get('multilang_manager');
    }

    /**
     * @return $this
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get code.
     *
     * @return null|string
     */
    public function getCode(): ?string
    {
        return $this->multiLangManager->getCurrentLang() ?? 'fr';
    }

    /**
     * Get label.
     *
     * @param string|null $code
     *
     * @return string
     */
    public function getLabel(?string $code): string
    {
        $lang = $this->multiLangManager->getLang($code);

        return $lang['label'] ?? '';
    }
}
