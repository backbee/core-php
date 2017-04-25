<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Title;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ParagraphHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!$this->supports($content)) {
            return;
        }

        if (isset($data['text'])) {

            $content->value = (string) $data['text'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content)
    {
        return [
            'text' => $content->value,
            'parameters' => array_map(function($param) {
                return $param['value'];
            }, $content->getAllParams())
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {

        if ($content instanceof Paragraph) {
            return true;
        }

        if ($content instanceof Title) {
            return true;
        }

        if ($content instanceof ArticleAbstract) {
            return true;
        }

        return false;
    }
}
