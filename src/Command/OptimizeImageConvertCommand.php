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

namespace BackBee\Command;

use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\CloudContentSet;
use BackBeePlanet\OptimizeImage\OptimizeImageManager;
use BackBeePlanet\OptimizeImage\OptimizeImageUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class OptimizeImageConvertCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class OptimizeImageConvertCommand extends AbstractCommand
{
    /**
     * @var OptimizeImageManager
     */
    protected $optimizeImageManager;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:oic')
            ->setDescription('Tries to convert all site images in order to optimize them.')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'The memory limit to set', 1);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->optimizeImageManager = $this->getContainer()->get('app.optimize_image.manager');
        $this->io = new SymfonyStyle($input, $output);

        // memory limit options
        if (null !== ($limit = $input->getOption('memory-limit'))) {
            ini_set('memory_limit', $limit);
        }

        // convert Basic\Image
        $this->io->section('Converting Basic\Image');
        $this->cleanMemoryUsage();
        $this->convertImages();
        $this->cleanMemoryUsage();
        $this->io->success('Basic\Image has been successfully converted.');

        // convert CloudContentSet
        $this->io->section('Converting CloudContentSet');
        $this->cleanMemoryUsage();
        $this->convertCloudContentSets();
        $this->cleanMemoryUsage();
        $this->io->success('CloudContentSet has been successfully converted.');

        return 0;
    }

    /**
     * Convert images.
     */
    protected function convertImages(): void
    {
        $contents = $this->getEntityManager()->getRepository(Image::class)->findAll();

        // set count
        $count = 1;

        foreach ($contents as $content) {
            if (0 === ($count % 20)) {
                $this->cleanMemoryUsage();
                $this->getEntityManager()->flush();
            }

            $startTime = microtime(true);

            // get media path
            $filePath = $this->optimizeImageManager->getMediaPath($content->image->path);

            // skipping obsolete image
            if (
                (empty($content->image->path)) ||
                (false === $this->optimizeImageManager->isValidToOptimize($filePath))
            ) {
                continue;
            }

            // convert images
            $this->optimizeImageManager->convertAllImages($filePath);

            $imageData = OptimizeImageUtils::replaceUploadDataExtension(
                [
                    'path' => $content->image->path,
                    'originalname' => $content->image->originalname,
                ],
                'jpg'
            );

            // set new image
            $content->image->path = $imageData['path'];
            $content->image->originalname = $imageData['originalname'];

            // save image
            $this->getEntityManager()->persist($content);

            $this->io->text(
                sprintf(
                    'Converting image "%s" (memory usage: %s - duration: %ss)',
                    $content->image->path,
                    $this->getPrettyMemoryUsage(),
                    number_format(microtime(true) - $startTime, 3)
                )
            );

            $count++;
        }

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
    }

    /**
     * Convert cloud content sets.
     */
    protected function convertCloudContentSets(): void
    {
        $contents = $this->getEntityManager()->getRepository(CloudContentSet::class)->findAll();

        // set count
        $count = 1;

        foreach ($contents as $content) {
            if (0 === ($count % 20)) {
                $this->cleanMemoryUsage();
                $this->getEntityManager()->flush();
            }

            $startTime = microtime(true);

            // get bg image path
            $bgImagePath = $content->getParam('bg_image');
            $bgImagePath = reset($bgImagePath);
            $filePath = $this->optimizeImageManager->getMediaPath($bgImagePath);

            // skipping obsolete bg image
            if (
                (empty($bgImagePath))
                || (false === $this->optimizeImageManager->isValidToOptimize($filePath))
            ) {
                continue;
            }

            // convert bg images
            $this->optimizeImageManager->convertAllImages($filePath);

            $imageData = OptimizeImageUtils::replaceUploadDataExtension(
                [
                    'path' => $bgImagePath,
                ],
                'jpg'
            );

            // set new bg image
            $content->setParam('bg_image', $imageData['path']);

            // save bg image
            $this->getEntityManager()->persist($content);

            $this->io->text(
                sprintf(
                    'Converting bg image "%s" (memory usage: %s - duration: %ss)',
                    $imageData['path'],
                    $this->getPrettyMemoryUsage(),
                    number_format(microtime(true) - $startTime, 3)
                )
            );

            $count++;
        }

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
    }
}
