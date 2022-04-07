<?php

/*
 * Copyright (c) 2022 Obione
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

use BackBee\ClassContent\Element\Image;
use Exception;

/**
 * Class rssImageHelper
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class rssImageHelper extends AbstractHelper
{
    /**
     * Invoke.
     *
     * @param array $imgData
     *
     * @return string
     */
    public function __invoke(array $imgData): string
    {
        if (null === $stat = $imgData['stat']) {
            $stat = [];
            $tmpfile = tempnam(sys_get_temp_dir(), 'img_');

            $imageUrl = sprintf(
                '%s:%s',
                $this->_renderer->getApplication()->getRequest()->getScheme(),
                $this->_renderer->getCdnImageUrl($imgData['url'])
            );
            file_put_contents($tmpfile, $imageUrl);
            $stat['mime_type'] = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpfile);
            $stat['filesize'] = filesize($tmpfile);

            $entyMgr = $this->_renderer->getApplication()->getEntityManager();

            try {
                $img = $entyMgr->find(Image::class, $imgData['uid']);
                $img->setParam('stat', $stat);
                $entyMgr->flush($img);
            } catch (Exception $exception) {
                $this->getRenderer()->getApplication()->getLogging()->error(
                    sprintf(
                        '%s : %s : %s',
                        __CLASS__, __FUNCTION__,
                        $exception->getMessage()
                    )
                );
            }
        }

        return sprintf(
            'type="%s" length="%d"',
            $stat['mime_type'],
            $stat['filesize']
        );
    }
}
