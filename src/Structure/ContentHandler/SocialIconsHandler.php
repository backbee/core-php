<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Social\Icons;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SocialIconsHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
         if (!$this->supports($content)) {
            return;
        }

        $result = $content->getParamValue('social');
        foreach ($data as $id => $url) {
            if (isset($result[$id])) {
                $result[$id]['url'] = (string) $url;
                $result[$id]['enable'] = true;
            }
        }

        $content->setParam('social', $result);
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
        return $content instanceof Icons;
    }
}
