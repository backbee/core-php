<?php

namespace BackBee\Renderer\Helper;

use BackBee\NestedNode\Page;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Renderer\Helper\AbstractHelper;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class seoMetadata extends AbstractHelper
{
    /**
     * @var \BackBeeCloud\Entity\PageManager
     */
    protected $pageManager;

    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $this->pageManager = $renderer->getApplication()->getContainer()->get('cloud.page_manager');
    }

    public function __invoke(Page $page)
    {
        $metadata = $this->pageManager->getPageSeoMetadata($page);
        if (isset($metadata['image_url']) && $metadata['image_url']) {
            $metadata['image_url'] = $this->_renderer->getCdnImageUrl($metadata['image_url']);
        }

        return $this->_renderer->partial('common/seo_metadata.html.twig', [
            'metadata' => $metadata,
        ]);
    }
}
