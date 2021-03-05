<?php

namespace BackBeeCloud\Listener;

use BackBee\Controller\Event\PostResponseEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class ContentCategoryListener
{
    /**
     * Default ordered content categories.
     *
     * @var array
     */
    private $defaultData = [
        'block_category_basics' => [
            'pos' => 0,
            'contents_order' => [
                'Basic/Title' => 0,
                'Text/Paragraph' => 1,
                'Basic/Image' => 2,
                'Text/Button' => 3,
                'Media/Video' => 4,
                'Basic/Slider' => 5,
                'Basic/ResponsiveSlider' => 6,
                'Basic/Searchbar' => 7,
                'Basic/Cards' => 8,
            ],
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
        'block_category_privacy_policy' => [
            'pos' => 4,
        ],
    ];

    /**
     * If true, only categories in $defaultData will be returned.
     *
     * @var boolean
     */
    private $strict;

    /**
     * Listener constructor
     *
     * @param array   $data     An ordered categories spec.
     * @param boolean $override Should the default data overrided? If false (default)
     *                          $data will be merged with default one.
     * @param boolean $strict   If true, only categories in $defaultData will be returned.
     */
    public function __construct(array $data = [], $override = false, $strict = true)
    {
        if (true === boolval($override)) {
            $this->defaultData = $data;
        } else {
            $this->defaultData = array_merge($this->defaultData, $data);
        }

        $this->strict = boolval($strict);
    }

    /**
     * Re-orders categories to set a custom order.
     *
     * @param PostResponseEvent $event
     */
    public function onGetCategoryPostCall(PostResponseEvent $event)
    {
        $result = [];
        $response = $event->getResponse();
        $decoded = json_decode($response->getContent(), true);
        $defaultData = $this->getCategoriesData();
        foreach ($decoded as $data) {
            // By default the category is pushed at the end of the array
            $pos = count($result) + count($decoded);

            if (array_key_exists($data['id'], $defaultData)) {
                $config = $defaultData[$data['id']];
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

                if (isset($config['pos'])) {
                    $pos = intval($config['pos']);
                }
            } elseif ($this->strict) {
                continue;
            }

            $data['contents'] = $this->filterContents($data['contents']);

            $result[$pos] = $data;
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
        return $this->defaultData;
    }

    /**
     * This method is overridable and allows developers to run custom process on
     * content row.
     *
     * {@see ::onGetCategoryPostCall()} at line 45
     *
     * @param array $content
     *
     * @return array
     */
    protected function runCustomProcessOnContent(array $content)
    {
        $content['thumbnail'] = null;

        return $content;
    }

    /**
     * Filters the set of contents.
     *
     * @param array $contents
     *
     * @return array
     */
    protected function filterContents($contents)
    {
        $filteredContents = [];
        $processedTypes = [];
        foreach ($contents as $content) {
            if (!in_array($content['type'], $processedTypes)) {
                $filteredContents[] = $this->runCustomProcessOnContent($content);
                $processedTypes[] = $content['type'];
            }
        }

        return $filteredContents;
    }
}
