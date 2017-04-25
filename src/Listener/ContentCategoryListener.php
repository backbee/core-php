<?php

namespace BackBeeCloud\Listener;

use BackBee\Controller\Event\PostResponseEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentCategoryListener
{
    protected static $categoriesOrder = [
        'block_category_basics'   => [
            'pos' => 0,
            'contents_order' => [
                'Basic/Title'     => 0,
                'Text/Paragraph'  => 1,
                'Media/Image'     => 2,
                'Text/Button'     => 3,
                'Media/Video'     => 4,
                'Basic/Slider'    => 5,
                'Basic/Searchbar' => 6,
            ]
        ],
        'block_category_pages' => [
            'pos' => 1,
        ],
        'block_category_social'   => [
            'pos' => 2,
        ],
        'block_category_more'     => [
            'pos' => 3,
        ],
    ];

    /**
     * Re-orders categories to set a custom order.
     *
     * @param  PostResponseEvent $event
     */
    public static function onGetCategoryPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        $result = [];
        foreach (json_decode($response->getContent(), true) as $data) {
            $id = $data['id'];
            if (array_key_exists($id, self::$categoriesOrder)) {
                $config = self::$categoriesOrder[$id];
                if (isset($config['contents_order'])) {
                    $contents = [];
                    foreach ($data['contents'] as $content) {
                        if (array_key_exists($content['type'], $config['contents_order'])) {
                            $contents[$config['contents_order'][$content['type']]] = $content;
                        }
                    }

                    ksort($contents);
                    $data['contents'] = $contents;
                }

                $result[$config['pos']] = $data;
            }
        }

        ksort($result);

        $response->setContent(json_encode($result));
    }
}
