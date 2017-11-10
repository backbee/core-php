<?php

namespace BackBeeCloud\Listener;

use BackBee\Controller\Event\PostResponseEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentCategoryListener
{
    /**
     * Re-orders categories to set a custom order.
     *
     * @param  PostResponseEvent $event
     */
    public function onGetCategoryPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        $result = [];
        $categoriesData = $this->getCategoriesData();
        foreach (json_decode($response->getContent(), true) as $data) {
            $id = $data['id'];
            if (array_key_exists($id, $categoriesData)) {
                $config = $categoriesData[$id];
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

                $filteredContents = [];
                $processedTypes = [];
                foreach ($data['contents'] as $content) {
                    if (!in_array($content['type'], $processedTypes)) {
                        $filteredContents[] = $this->runCustomProcessOnContent($content);
                        $processedTypes[] = $content['type'];
                    }
                }

                $data['contents'] = $filteredContents;
                $result[$config['pos']] = $data;
            }
        }

        ksort($result);

        $response->setContent(json_encode($result));
    }

    /**
     * Returns contents categories data.
     *
     * @return array
     */
    protected function getCategoriesData()
    {
        return [
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
            'block_category_social' => [
                'pos' => 2,
            ],
            'block_category_more' => [
                'pos' => 3,
            ],
        ];
    }

    /**
     * This method is overridable and allows developers to run custom process on
     * content row.
     *
     * {@see ::onGetCategoryPostCall()} at line 45
     *
     * @param  array  $content
     *
     * @return array
     */
    protected function runCustomProcessOnContent(array $content)
    {
        $content['thumbnail'] = null;

        return $content;
    }
}
