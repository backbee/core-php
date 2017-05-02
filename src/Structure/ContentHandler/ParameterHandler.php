<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ParameterHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!isset($data['parameters']) || !is_array($data['parameters'])) {
            return;
        }

        foreach ($data['parameters'] as $attr => $value) {
            $content->setParam($attr, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content)
    {
        $all = $content->getAllParams();

        return [
            'parameters' => array_map(function($param) {
                $result = $param['value'];
                if (isset($param['type']) && 'selectTag' === $param['type']) {
                    $result = array_map(function ($value) {
                        return isset($value['label']) ? $value['label'] : $value;
                    }, $result);
                }

                return $result;
            }, $all),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return true;
    }
}
