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

use BackBee\Bundle\Registry;
use Exception;

/**
 * Class sitename
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class sitename extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @return string
     */
    public function __invoke(): string
    {
        $app = $this->_renderer->getApplication();
        $siteName = $app->getSite() ? $app->getSite()->getLabel() : '';

        try {
            $registry = $app->getEntityManager()->getRepository(Registry::class)->findOneBy([
                'key' => 'site_label',
                'scope' => 'GLOBAL',
            ]);

            if ($registry && trim($registry->getValue())) {
                $siteName = $registry->getValue();
            }

        } catch (Exception $exception) {
            $app->getLogging()->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $siteName;
    }
}
