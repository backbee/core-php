<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Image;
use BackBee\Config\Config;
use BackBee\FileSystem\ImageHandlerInterface;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * Class ImageHandler
 *
 * @package BackBeeCloud\Structure\ContentHandler
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImageHandler implements ContentHandlerInterface
{
    /**
     * @var ImageHandlerInterface
     */
    protected $imgUploadHandler;

    /**
     * @var ParameterHandler
     */
    protected $paramHandler;

    /**
     * @var Config
     */
    protected $config;

    /**
     * ImageHandler constructor.
     *
     * @param ImageHandlerInterface $imgUploadHandler
     * @param ParameterHandler      $paramHandler
     * @param Config                $config
     */
    public function __construct(
        ImageHandlerInterface $imgUploadHandler,
        ParameterHandler $paramHandler,
        Config $config
    ) {
        $this->imgUploadHandler = $imgUploadHandler;
        $this->paramHandler = $paramHandler;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data, $handleParameters = false)
    {
        if (!$this->supports($content)) {
            return;
        }

        if (isset($data['path']) && false !== $data['path']) {
            if (1 === preg_match('~^https?://~', $data['path'])) {
                $content->image->path = $this->imgUploadHandler->uploadFromUrl($data['path']);
            } else {
                $content->image->path = '/static/theme-default-resources/' . $data['path'];
            }

            $content->image->originalname = basename($data['path']);
        }

        if ($handleParameters) {
            $this->paramHandler->handle($content, $data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $config = [], $handleParameters = false)
    {
        $result = $config['current_data'] ?? [];

        $settings = $this->config->getSection('cdn');

        $filename = '';
        if ($path = ltrim($content->image->path, '/')) {
            $filename = $config['uploadCallback']($settings['image_domain'] . '/' . $path);
        }

        $result['path'] = false === $filename ? '' : $config['themeName'] . '/' . $filename;

        if ($handleParameters) {
            $result = array_merge($result, $this->paramHandler->handleReverse($content));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Image;
    }
}
