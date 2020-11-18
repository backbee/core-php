<?php

namespace BackBeePlanet\Listener;

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Renderer\Event\RendererEvent;
use BackBeeCloud\UserAgentHelper;
use BackBeePlanet\GlobalSettings;
use BackBeePlanet\OptimizeImage\OptimizeImageManager;
use BackBeePlanet\OptimizeImage\OptimizeImageUtils;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class OptimizeImageListener
{
    /**
     * @var OptimizeImageManager
     */
    private $optimizeImageManager;

    /**
     * OptimizeImageListener constructor.
     *
     * @param OptimizeImageManager $optimizeImageManager
     */
    public function __construct(OptimizeImageManager $optimizeImageManager)
    {
        $this->optimizeImageManager = $optimizeImageManager;
    }

    /**
     * On Application init.
     */
    public function onApplicationInit(): void
    {
        $settings = (array)(new GlobalSettings())->optimizeimage();

        if (false === $settings) {
            throw new \RuntimeException(
                sprintf(
                    '[%s] Incomplete settings provided inside global_settings.',
                    self::class
                )
            );
        }
    }

    /**
     * On cloud content set render.
     *
     * @param RendererEvent $event
     */
    public function onCloudContentSetRender(RendererEvent $event): void
    {
        $cloudContentSet = $event->getTarget();
        if (false === $bgImageUrl = $cloudContentSet->getParamValue('bg_image')) {
            return;
        }

        $filePath = $this->optimizeImageManager->getMediaPath($bgImageUrl);

        // skipping transparency png and animated gif...
        if (false === $this->optimizeImageManager->isValidToOptimize($filePath)) {
            return;
        }

        // get settings
        $colsizesSettings = $this->optimizeImageManager->getSettings()['colsizes'];
        $browserColsizesSettings = $this->optimizeImageManager->getSettings()['browsercolsizes'];

        // $filesystem = new Filesystem();
        // $size = $colsizesSettings[$browserColsizesSettings['max']];
        // switch (UserAgentHelper::getDeviceType()) {
        //     case 'mobile':
        //         $size = $colsizesSettings[$browserColsizesSettings['mid']];
        //         break;
        //     default:
        //         break;
        // }

        // $filename = OptimizeImageUtils::genericSizeFilename($bgImageUrl, $size, 'jpg');

        if ('mobile' === UserAgentHelper::getDeviceType()) {
            $filename = OptimizeImageUtils::genericSizeFilename(
                $bgImageUrl,
                $colsizesSettings[$browserColsizesSettings['mid']],
                'jpg'
            );

            // if (false === $filesystem->exists($this->optimizeImageManager->getMediaPath($filename))) {
            //     $this->optimizeImageManager->convertAllImages($filePath);
            // }

            $cloudContentSet->setParam('bg_image', $filename);
        }
    }

    /**
     * On basic image render.
     *
     * @param RendererEvent $event
     */
    public function onBasicImageRender(RendererEvent $event): void
    {
        $image = $event->getTarget();
        $renderer = $event->getRenderer();
        $renderer->assign('image_full_width_path', $image->image->path);
        $image->image->path = $this->optimizeImageManager->getOptimizeImagePath(
            (string) $image->image->path,
            (bool) $renderer->getParam('in_fluid'),
            (int) $renderer->getParam('colsize')
        );
    }

    /**
     * On image upload post call.
     *
     * @param PostResponseEvent $event
     */
    public function onImageUploadPostCall(PostResponseEvent $event): void
    {
        $response = $event->getResponse();
        $imageData = json_decode($response->getContent(), true);

        $filePath = $this->optimizeImageManager->getMediaPath($imageData['path']);

        // skipping transparency png and animated gif...
        if (false === $this->optimizeImageManager->isValidToOptimize($filePath)) {
            return;
        }

        $this->optimizeImageManager->convertAllImages($filePath);

        // replace extension
        $imageData = OptimizeImageUtils::replaceUploadDataExtension($imageData, 'jpg');

        $response->setContent(json_encode($imageData));
    }
}