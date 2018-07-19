<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Basic\Slider;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SliderHandler implements ContentHandlerInterface
{
    use \BackBeeCloud\Structure\ClassContentHelperTrait;

    protected $imgHandler;

    public function __construct(ImageHandler $imgHandler)
    {
        $this->imgHandler = $imgHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!$this->supports($content)) {
            return;
        }

        $images = [];
        if (isset($data['images'])) {
            foreach ($data['images'] as $rawData) {
                $newImage = $this->createOnlineContent(Image::class);
                $this->imgHandler->handle($newImage, $rawData, true);
                $images[] = $newImage;
            }
        }

        $content->images = $images;
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $config = [])
    {
        $result = isset($config['current_data']) ? $config['current_data'] : [];
        unset($config['current_data']);

        $result['images'] = [];
        foreach((array) $content->images as $image) {
            $result['images'][] = $this->imgHandler->handleReverse($image, $config, true);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Slider;
    }
}
