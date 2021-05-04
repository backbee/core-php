<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

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
