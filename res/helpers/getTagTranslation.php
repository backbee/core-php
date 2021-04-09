<?php

namespace BackBee\Renderer\Helper;

use BackBee\NestedNode\Keyword as Tag;
use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Tag\TagLang;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

/**
 * Class getTagTranslation
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class getTagTranslation extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @param Tag|null $tag
     * @param          $lang
     *
     * @return string|null
     */
    public function __invoke(?Tag $tag, $lang): ?string
    {
        $entityManager = $this->_renderer->getApplication()->getEntityManager();

        if (!$tag instanceof Tag || !$entityManager instanceof EntityManagerInterface) {
            return null;
        }

        $result = $tag->getKeyWord();

        try {
            if ($lang && $lang = $entityManager->find(Lang::class, $lang)) {
                $tagLang = $entityManager->getRepository(TagLang::class)->findOneBy(compact('tag', 'lang'));
                if ($tagLang) {
                    $result = $tagLang->getTranslation();
                }
            }
        } catch (Exception $exception) {
            $this->_renderer->getApplication()->getLogging()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $result;
    }
}
