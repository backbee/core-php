<?php

namespace BackBee\Renderer\Helper;

use BackBee\NestedNode\Page;

/**
 * Class sitemapGetImagesByPage
 *
 * @package BackBee\Renderer\Helper
 */
class sitemapGetImagesByPage extends AbstractHelper
{
    /**
     * @param Page $page
     *
     * @return array|null
     */
    public function __invoke(Page $page): ?array
    {
        $images = $this->getRenderer()
            ->getApplication()
            ->getContainer()
            ->get('elasticsearch.manager')
            ->getAllImageForAnPageByUid($page->getUid());

        return array_map(static function ($image) {
            return $image['path'] ?? '';
        }, $images);
    }
}
