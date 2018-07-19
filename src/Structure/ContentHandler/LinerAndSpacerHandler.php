<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Liner;
use BackBee\ClassContent\Basic\Spacer;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class LinerAndSpacerHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        // nothing specific to do...
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $data = [])
    {
        $result = isset($data['current_data']) ? $data['current_data'] : [];
        $result['parameters']['height'] = 26;

        if (isset($result['parameters']['height']) && 40 > $result['parameters']['height']) {
            unset($result['parameters']['height']);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Liner || $content instanceof Spacer;
    }
}
