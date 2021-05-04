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

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBeeCloud\Design\FontManager;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class DesignFontController extends AbstractController
{
    /**
     * @var FontManager
     */
    protected $fontManager;

    public function __construct(FontManager $fontManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->fontManager = $fontManager;
    }

    public function getAllAction()
    {
        $this->assertIsAuthenticated();

        return new JsonResponse($this->fontManager->all());
    }
}
