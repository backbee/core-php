<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Slider;
use BackBee\ClassContent\Media\Image;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SliderHandler implements ContentHandlerInterface
{
    use \BackBeeCloud\Structure\ClassContentHelperTrait;

    protected $imgHandler;
    protected $paramHandler;

    public function __construct(ImageHandler $imgHandler, ParameterHandler $paramHandler)
    {
        $this->imgHandler = $imgHandler;
        $this->paramHandler = $paramHandler;
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
                $this->imgHandler->handle($newImage, $rawData);
                $this->paramHandler->handle($newImage, $rawData);
                $images[] = $newImage;
            }
        }

        $content->images = $images;
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, $config = [])
    {
        $object = [
            'images' => [],
        ];

        foreach($content->images as $image) {
            $object['images'][] = $this->imgHandler->handleReverse($image, $config);
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Slider;
    }
}
