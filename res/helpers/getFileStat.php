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

use BackBee\Util\File\File;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Entity\ContentManager;
use Doctrine\ORM\EntityManager;

/**
 * Class getFileStat
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class getFileStat extends AbstractHelper
{
    /**
     * @var File
     */
    protected $file;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var ContentManager
     */
    protected $contentManager;

    /**
     * @var ElasticsearchManager
     */
    protected $elasticSearchManager;

    /**
     * @param null $file
     *
     * @return array|false
     */
    public function __invoke($file = null)
    {
        $this->entityManager = $this->getRenderer()->getApplication()->getEntityManager();
        $this->contentManager = $this->getRenderer()->getApplication()->getContainer()->get('cloud.content_manager');
        $this->elasticSearchManager = $this->getRenderer()->getApplication()->getContainer()->get(
            'elasticsearch.manager'
        );

        $filepath = str_replace(
            ['/media/', '/img/'],
            ['/Media/', '/Media/'],
            $this->getDataDir() . $file['value']
        );

        if (file_exists($filepath) && $stat = stat($filepath)) {
            $stat['sizeHumanReadable'] = $this->humanReadableFileSize($stat['size']);
            $stat['extension'] = pathinfo($filepath, PATHINFO_EXTENSION);
        }

        return $stat ?? [];
    }

    /**
     * Returns structures base directory.
     *
     * @return string
     */
    protected function getDataDir(): string
    {
        return $this->getRenderer()->getApplication()->getDataDir();
    }

    /**
     * Returns a human readable file size.
     *
     * @param $bytes
     *
     * @return string
     */
    protected function humanReadableFileSize($bytes): string
    {
        $kilobyte = 1024;
        $megabyte = $kilobyte * 1024;
        $gigabyte = $megabyte * 1024;
        $terabyte = $gigabyte * 1024;

        if ($bytes < $kilobyte) {
            $readableSize = $bytes . ' B';
        } elseif ($bytes < $megabyte) {
            $readableSize = (number_format($bytes / $kilobyte, 2, ',', ' ')) . ' KB';
        } elseif ($bytes < $gigabyte) {
            $readableSize = (number_format($bytes / $megabyte, 2, ',', ' ')) . ' MB';
        } elseif ($bytes < $terabyte) {
            $readableSize = (number_format($bytes / $gigabyte, 2, ',', ' ')) . ' GB';
        } else {
            $readableSize = (number_format($bytes / $terabyte, 2, ',', ' ')) . ' TB';
        }

        return $readableSize;
    }
}
