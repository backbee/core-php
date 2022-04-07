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

namespace BackBee\Installer;

use App\Helper\StandaloneHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class AssetsInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class AssetsInstaller extends AbstractInstaller
{
    /**
     * Install.
     *
     * @param SymfonyStyle $io
     */
    public function install(SymfonyStyle $io): void
    {
        $io->section('Install assets');

        $rootDirectory = StandaloneHelper::rootDir();
        $assetsDirectory = $rootDirectory . '/assets';
        StandaloneHelper::mkdirOnce($assetsDirectory);

        $filesystem = new Filesystem();

        // CSS process...
        $cssDirectory = $assetsDirectory . '/css';
        StandaloneHelper::mkdirOnce($cssDirectory);
        $cssCommonDirectory = $cssDirectory . '/common';

        $filesystem->mirror(
            $this->getCssAssetsDirectory(),
            $cssCommonDirectory,
            null,
            [
                'override' => true,
                'delete' => true,
            ]
        );

        // JS process...
        $jsDirectory = $assetsDirectory . '/js';
        StandaloneHelper::mkdirOnce($jsDirectory);
        $jsCommonDirectory = $jsDirectory . '/common';

        $filesystem->mirror(
            $this->getJsAssetsDirectory(),
            $jsCommonDirectory,
            null,
            [
                'override' => true,
                'delete' => true,
            ]
        );

        // Fonts process...
        $fontsDirectory = $assetsDirectory . '/fonts';
        StandaloneHelper::mkdirOnce($fontsDirectory);
        $fontsCommonDirectory = $fontsDirectory . '/common';

        $filesystem->mirror(
            $this->getFontsAssetsDirectory(),
            $fontsCommonDirectory,
            null,
            [
                'override' => true,
                'delete' => true,
            ]
        );

        $io->success('Site assets successfully installed/updated.');
    }

    /**
     * Get assets directory.
     *
     * @return string
     */
    public function getAssetsDirectory(): string
    {
        return __DIR__ . '/../../assets';
    }

    /**
     * Get css assets directory.
     *
     * @return string
     */
    public function getCssAssetsDirectory(): string
    {
        return $this->getAssetsDirectory() . '/css';
    }

    /**
     * Get js assets directory.
     *
     * @return string
     */
    public function getJsAssetsDirectory(): string
    {
        return $this->getAssetsDirectory() . '/js';
    }

    /**
     * Get fonts assets directory.
     *
     * @return string
     */
    public function getFontsAssetsDirectory(): string
    {
        return $this->getAssetsDirectory() . '/fonts';
    }
}