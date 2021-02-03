<?php

namespace BackBeePlanet\Command;

use BackBee\BBApplication;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\CloudContentSet;
use BackBeePlanet\OptimizeImage\OptimizeImageManager;
use BackBeePlanet\OptimizeImage\OptimizeImageUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

/**
 * Class ConvertCommandHandler
 *
 * @package BackBeePlanet\Command
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class ConvertCommandHandler
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var OptimizeImageManager
     */
    protected $optimizeImageManager;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Args
     */
    protected $args;

    /**
     * @var IO
     */
    protected $io;

    /**
     * ConvertCommandHandler constructor.
     *
     * @param BBApplication $app
     */
    public function __construct(BBApplication $app)
    {
        $this->app = $app;
        $this->entityManager = $app->getEntityManager();
        $this->optimizeImageManager = $app->getContainer()->get('app.optimize_image.manager');
        $this->filesystem = new Filesystem();
    }

    /**
     * Print help command.
     *
     * @param Args $args
     * @param IO   $io
     *
     * @return int
     */
    public function handle(Args $args, IO $io): int
    {
        $io->writeLine(
            '<info>Run `console optimize-image --help` to display the list of available images command.</info>'
        );

        return 0;
    }

    /**
     * Execute the command.
     *
     * @param Args $args
     * @param IO   $io
     *
     * @return int
     * @throws OptimisticLockException
     */
    public function handleConvert(Args $args, IO $io): int
    {
        $this->args = $args;
        $this->io = $io;

        // memory limit options
        if (null !== $limit = $args->getOption('memory-limit')) {
            ini_set('memory_limit', $limit);
        }

        // convert Basic\Image
        $this->io->writeLine('<c1>Converting Basic\Image...</c1>');
        $this->cleanMemoryUsage();
        $contents = $this->entityManager->getRepository(Image::class)->findAll();
        $this->convertImages($contents);
        $this->cleanMemoryUsage();
        $this->io->writeLine('<c1>Basic\Image has been successfully converted.</c1>');
        $this->io->writeLine('');

        // convert CloudContentSet
        $this->io->writeLine('<c1>Converting CloudContentSet...</c1>');
        $this->cleanMemoryUsage();
        $contents = $this->entityManager->getRepository(CloudContentSet::class)->findAll();
        $this->convertCloudContentSets($contents);
        $this->cleanMemoryUsage();
        $this->io->writeLine('<c1>CloudContentSet has been successfully converted.</c1>');

        return 0;
    }

    /**
     * Convert images.
     *
     * @param array $contents
     *
     * @throws OptimisticLockException
     */
    protected function convertImages(array $contents): void
    {
        // set count
        $count = 1;

        foreach ($contents as $content) {
            if (0 === ($count % 20)) {
                $this->cleanMemoryUsage();
                $this->entityManager->flush();
            }

            $starttime = microtime(true);

            // get media path
            $filePath = $this->optimizeImageManager->getMediaPath($content->image->path);

            // skipping obsolete image
            if (
                (empty($content->image->path))
                || (false === $this->optimizeImageManager->isValidToOptimize($filePath))
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
            $this->entityManager->persist($content);

            $this->io->writeLine(
                sprintf(
                    '    > Converting image <c2>"%s"</c2> (memory usage: %s - duration: %ss)',
                    $content->image->path,
                    $this->getPrettyMemoryUsage(),
                    number_format(microtime(true) - $starttime, 3)
                )
            );

            $count++;
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Convert cloud content sets.
     *
     * @param array $contents
     * @throws OptimisticLockException
     */
    protected function convertCloudContentSets(array $contents): void
    {
        // set count
        $count = 1;

        foreach ($contents as $content) {
            if (0 === ($count % 20)) {
                $this->cleanMemoryUsage();
                $this->entityManager->flush();
            }

            $starttime = microtime(true);

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
            $this->entityManager->persist($content);

            $this->io->writeLine(
                sprintf(
                    '    > Converting bg image <c2>"%s"</c2> (memory usage: %s - duration: %ss)',
                    $imageData['path'],
                    $this->getPrettyMemoryUsage(),
                    number_format(microtime(true) - $starttime, 3)
                )
            );

            $count++;
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Clean memory usage.
     */
    protected function cleanMemoryUsage(): void
    {
        gc_collect_cycles();
        gc_disable();
        gc_enable();
    }

    /**
     * Get pretty memory usage.
     *
     * @return string
     */
    protected function getPrettyMemoryUsage(): string
    {
        $size = memory_get_usage();
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }
}