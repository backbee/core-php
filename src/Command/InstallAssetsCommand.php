<?php

namespace BackBee\Command;

use BackBeePlanet\Standalone\StandaloneHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class InstallAssetsCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class InstallAssetsCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:install-assets')
            ->setAliases(['bb:ia'])
            ->setDescription('[bb:ia] - Install or update project front assets');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);
        $templateManager = $this->getContainer()->get('core.template.manager');

        $rootDirectory = StandaloneHelper::rootDir();
        $assetsDirectory = $rootDirectory . '/assets';
        StandaloneHelper::mkdirOnce($assetsDirectory);

        $filesystem = new Filesystem();

        // CSS process...
        $cssDirectory = $assetsDirectory . '/css';
        StandaloneHelper::mkdirOnce($cssDirectory);
        $cssCommonDirectory = $cssDirectory . '/common';

        $filesystem->mirror(
            $templateManager->getCssAssetsDirectory(),
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
            $templateManager->getJsAssetsDirectory(),
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
            $templateManager->getFontsAssetsDirectory(),
            $fontsCommonDirectory,
            null,
            [
                'override' => true,
                'delete' => true,
            ]
        );

        $io->success('Site assets successfully installed/updated.');

        return 0;
    }
}
