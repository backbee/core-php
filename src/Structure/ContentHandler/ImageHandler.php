<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Media\Image;
use BackBeeCloud\Structure\ContentHandlerInterface;
use BackBeeCloud\ImageHandlerInterface;

use BackBeePlanet\GlobalSettings;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImageHandler implements ContentHandlerInterface
{
    /**
     * @var ImageHandlerInterface
     */
    protected $imgUploadHandler;

    public function __construct(ImageHandlerInterface $imgUploadHandler)
    {
        $this->imgUploadHandler = $imgUploadHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!$this->supports($content)) {
            return;
        }

        $settings = (new GlobalSettings())->cdn();

        if (isset($data['path']) && false != $data['path']) {
            if (1 === preg_match('~^https?://~', $data['path'])) {
                $content->path = $this->imgUploadHandler->uploadFromUrl($data['path']);
            } else {
                $content->path = '/static/theme-default-resources/' . $data['path'];
            }

            $content->originalname = basename($data['path']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, $config = [])
    {
        $settings = (new GlobalSettings())->cdn();

        $filename = '';
        if ($path = ltrim($content->path, '/')) {
            $filename = $config['uploadCallback']($settings['image_domain'] . '/' . $path);
        }

        $params = $content->getAllParams();
        unset($params['image_small'], $params['image_medium']);

        return [
            'path' => false == $filename ? '' : $config['themeName'] . '/' . $filename,
            'parameters' => array_map(function($param) {
                return $param['value'];
            }, $params)
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Image;
    }
}
