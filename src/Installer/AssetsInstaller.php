<?php

namespace BackBee\Installer;

use BackBeePlanet\Standalone\StandaloneHelper;
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