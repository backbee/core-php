<?php

namespace BackBee\Renderer\Helper;

use BackBee\ClassContent\Media\Image;
use BackBeePlanet\GlobalSettings;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class rssImageHelper extends AbstractHelper
{
    public function __invoke(array $imgData)
    {
        if (null === $stat = $imgData['stat']) {
            $stat = [];
            $tmpfile = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tmpfile, file_get_contents($this->_renderer->getCdnImageUrl($imgData['url'], true)));
            $stat['mime_type'] = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpfile);
            $stat['filesize'] = filesize($tmpfile);

            $entyMgr = $this->_renderer->getApplication()->getEntityManager();
            $img = $entyMgr->find(Image::class, $imgData['uid']);
            $img->setParam('stat', $stat);
            $entyMgr->flush($img);
        }

        return sprintf(
            'type="%s" length="%d"',
            $stat['mime_type'],
            $stat['filesize']
        );
    }
}
