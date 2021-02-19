<?php

namespace BackBee\KnowledgeGraph\Schema;

use BackBee\Renderer\Renderer;
use Datetime;
use Exception;

/**
 * Class SchemaWebPage
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaWebPage implements SchemaInterface
{
    /**
     * @var SchemaContext
     */
    private $context;

    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * SchemaWebPage constructor.
     *
     * @param SchemaContext $context
     */
    public function __construct(SchemaContext $context)
    {
        $this->context = $context;
        $this->renderer = $context->getApplication()->getRenderer();
    }

    /**
     * Returns the WebPage  Schema data.
     *
     * @return array $data The WebPage schema.
     * @throws Exception
     */
    public function generate(): array
    {
        $app = $this->context->getApplication();
        $cxData = $this->context->getData();

        return [
            '@type' => 'WebPage',
            '@id' => $this->renderer->getUri($cxData['url']) . SchemaIds::WEBPAGE_HASH,
            'name' => $cxData['title'],
            'url' => $this->renderer->getUri($cxData['url']),
            'isPartOf' => [
                '@id' => $this->renderer->getUri('/') . SchemaIds::WEBSITE_HASH,
            ],
            'datePublished' => (new Datetime($cxData['published_at']))->format('c'),
            'dateModified' => (new Datetime($cxData['modified_at']))->format('c'),
        ];
    }
}
