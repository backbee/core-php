<?php

namespace BackBee\Renderer\Helper;

use BackBee\NestedNode\Page;
use function number_format;

/**
 * Class sitemapLocationPriority
 *
 * Helper computing a priority for pages in sitemaps.
 *
 * @package BackBee\Renderer\Helper
 */
class sitemapLocationPriority extends AbstractHelper
{
    /**
     * Return a computed priority for a page:
     *  * for Home, return 1.0
     *  * for page in the first level of the menu, return 0.8
     *  * for others return 0.5
     *
     * @param Page|null $page
     *
     * @return string
     */
    public function __invoke(Page $page = null): string
    {
        if (null === $page || 1 < $page->getLevel()) {
            return '0.5';
        }

        $priority = 1 / (1 + ($page->getLevel() * $page->getState() / (Page::STATE_ONLINE + Page::STATE_HIDDEN)));

        return number_format($priority, 1);
    }
}
