<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBeeCloud\Structure\ContentHandlerInterface;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Cards;
use BackBee\ClassContent\Basic\Image;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CardsHandler implements ContentHandlerInterface
{
    use \BackBeeCloud\Structure\ClassContentHelperTrait;

    /**
     * @var ImageHandler
     */
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

        if (isset($data['image'])) {
            $newImage = $this->createOnlineContent(Image::class);
            $this->imgHandler->handle($newImage, $data['image'], true);
            $content->image = $newImage;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $config = [])
    {
        $result = isset($config['current_data']) ? $config['current_data'] : [];

        if ($content->image->image->path) {
            $result['image'] = $this->imgHandler->handleReverse($content->image, $config, true);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Cards;
    }
}
