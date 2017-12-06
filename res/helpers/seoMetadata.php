<?php

namespace BackBee\Renderer\Helper;

use BackBeeCloud\PageType\ArticleType;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Article\ArticleTitle;
use BackBee\ClassContent\Media\Image;
use BackBee\NestedNode\Page;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Renderer\Helper\AbstractHelper;
use Elasticsearch\Common\Exceptions\Missing404Exception;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class seoMetadata extends AbstractHelper
{
    protected $entyMgr;
    protected $elasticsearchMgr;

    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $this->entyMgr = $renderer->getApplication()->getEntityManager();
        $this->elasticsearchMgr = $renderer->getApplication()->getContainer()->get('elasticsearch.manager');
    }

    public function __invoke(Page $page)
    {
        $result = $this->overrideByPage($page, [
            'title'       => $page->getTitle(),
            'description' => '',
        ]);

        $bag = $page->getMetaData() ?: [];
        foreach ($bag as $attr => $metadata) {
            if ($metadata->getAttribute('name') === $attr) {
                if ($value = $metadata->getAttribute('content')) {
                    $result[$attr] = $value;
                }
            }
        }

        return $this->_renderer->partial('common/seo_metadata.html.twig', [
            'metadata' => $result,
        ]);
    }

    protected function overrideByPage(Page $page, array $result)
    {
        $data = null;
        try {
            $data = $this->elasticsearchMgr->getClient()->get([
                'id'    => $page->getUid(),
                'type'  => $this->elasticsearchMgr->getPageTypeName(),
                'index' => $this->elasticsearchMgr->getIndexName(),
            ]);
        } catch (Missing404Exception $e) {
            // nothing to do...
        }

        if (null === $data || !$data['found']) {
            return $result;
        }

        $data = $data['_source'];

        if ('article' !== $data['type']) {
            return $result;
        }

        $result['title'] = $data['title'];

        if (null !== $data['image_uid']) {
            $image = $this->entyMgr->find(Image::class, $data['image_uid']);
            if (null !== $image) {
                $result['image_url'] = $this->_renderer->getCdnImageUrl($image->path);
            }
        }

        if (null !== $data['abstract_uid']) {
            $abstract = $this->entyMgr->find(AbstractClassContent::class, $data['abstract_uid']);
            if (null !== $abstract) {
                $result['description'] = html_entity_decode(strip_tags($abstract->value));
            }
        }

        return $result;
    }
}
