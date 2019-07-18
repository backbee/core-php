<?php

namespace BackBee\Renderer\Helper;

use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Tag\TagLang;
use BackBee\NestedNode\Keyword as Tag;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Helper\AbstractHelper;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class getTagTranslation extends AbstractHelper
{
    public function __invoke(Tag $tag, $lang)
    {

        $result = $tag->getKeyWord();

        if ($lang) {
            $entityManager = $this->_renderer->getApplication()->getEntityManager();
            if ($lang = $entityManager->find(Lang::class, $lang)) {
                $tagLang = $entityManager->getRepository(TagLang::class)->findOneBy([
                    'tag' => $tag,
                    'lang' => $lang,
                ]);

                if ($tagLang) {
                    $result = $tagLang->getTranslation();
                }
            }
        }

        return $result;
    }
}
