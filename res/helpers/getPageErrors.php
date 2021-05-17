<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

/**
 * Class getPageErrors
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class getPageErrors extends AbstractHelper
{
    public const DESCRIPTION = [
        '404' => 'page.errors.404.message',
        '500' => '',
    ];

    public const BUTTON_TITLE = 'page.errors.button';

    /**
     * Invoke.
     *
     * @param string $code
     *
     * @return array
     */
    public function __invoke(string $code = ''): array
    {
        $app = $this->_renderer->getApplication();
        $container = $app->getContainer();

        $userPrefMgr = $container->get('user_preference.manager');
        $result = $userPrefMgr->dataOf('error_page_' . $code);

        $data = [
            'title' => $code,
            'description' => self::DESCRIPTION[$code],
            'button_title' => self::BUTTON_TITLE,
        ];

        if (false !== $result) {
            if (isset($result['title']) && false !== $result['title']) {
                $data['title'] = $result['title'];
            }

            if (isset($result['description']) && false !== $result['description']) {
                $data['description'] = $result['description'];
            }

            if (isset($result['button_title']) && false !== $result['button_title']) {
                $data['button_title'] = $result['button_title'];
            }
        }

        return $data;
    }
}
