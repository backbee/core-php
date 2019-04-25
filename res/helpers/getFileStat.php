<?php

namespace BackBee\Renderer\Helper;

use Doctrine\ORM\EntityManager;
use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;

/**
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
     * @param  File $file
     *
     * @return getFileStat||boolean
     */
    public function __invoke($file = null)
    {
        $this->entityManager = $this->getRenderer()->getApplication()->getEntityManager();
        $this->contentManager = $this->getRenderer()->getApplication()->getContainer()->get('cloud.content_manager');
        $this->elasticSearchManager = $this->getRenderer()->getApplication()->getContainer()->get('elasticsearch.manager');

        $filepath = str_replace('/img/', '/Media/', $this->getDataDir() . $file['value']);
        $stat = stat($filepath);
        $stat['sizeHumanReadable'] = $this->humanReadableFileSize($stat['size']);
        $stat['extension'] = pathinfo($filepath, PATHINFO_EXTENSION);
        return $stat;
    }

    /**
     * Returns structures base directory.
     *
     * @return string
     */
    protected function getDataDir()
    {
        return $this->getRenderer()->getApplication()->getDataDir();
    }

    /**
     * Returns a human readable file size.
     *
     * @return string
     */
    protected function humanReadableFileSize($bytes)
    {
        $kilobyte = 1024;
        $megabyte = $kilobyte * 1024;
        $gigabyte = $megabyte * 1024;
        $terabyte = $gigabyte * 1024;

        $readableSize = null;

        if ($bytes < $kilobyte) {
            $readableSize = $bytes . ' B';
        } else if ($bytes < $megabyte) {
            $readableSize = (number_format($bytes / $kilobyte, 2, ',', ' ')) . ' KB';
        } else if ($bytes < $gigabyte) {
            $readableSize = (number_format($bytes / $megabyte, 2, ',', ' ')) . ' MB';
        } else if ($bytes < $terabyte) {
            $readableSize = (number_format($bytes / $gigabyte, 2, ',', ' ')) . ' GB';
        } else {
            $readableSize = (number_format($bytes / $terabyte, 2, ',', ' ')) . ' TB';
        }

        return $readableSize;
    }
}
