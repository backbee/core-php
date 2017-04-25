<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Content\HighlightContent;
use BackBee\NestedNode\Page;
use BackBeeCloud\Structure\ContentHandlerInterface;


/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class HighlightContentHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!$this->supports($content)) {
            return;
        }

        if (isset($data['parameters']) && isset($data['parameters']['content'])) {
            $pageTitle = $data['parameters']['content']['title'];

            $content->setParam('content', [
                'id'    => md5($pageTitle),
                'title' => $pageTitle,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof HighlightContent;
    }
}
