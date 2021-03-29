<?php

namespace BackBeePlanet\Standalone;

use BackBeeCloud\Listener\ContentCategoryListener as BaseContentCategoryListener;
use BackBeePlanet\Standalone\AbstractStandaloneHelper;
use BackBee\Routing\RouteCollection;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentCategoryListener extends BaseContentCategoryListener
{
    const STANDALONE_HELPER_CLASSNAME = 'BackBeePlanet\Standalone\StandaloneHelper';

    /**
     * @var RouteCollection
     */
    protected $routing;

    /**
     * Constructor.
     *
     * @param RouteCollection $routing
     */
    public function __construct(array $data = [], $override = false, $strict = true, RouteCollection $routing)
    {
        if (!class_exists(self::STANDALONE_HELPER_CLASSNAME)) {
            throw new \RuntimeException(sprintf(
                'Class %s is needed for %s to work.',
                self::STANDALONE_HELPER_CLASSNAME,
                static::class
            ));
        }

        if (!is_subclass_of(self::STANDALONE_HELPER_CLASSNAME, AbstractStandaloneHelper::class)) {
            throw new \RuntimeException(sprintf(
                'Class %s must extend %s abstract class to work.',
                self::STANDALONE_HELPER_CLASSNAME,
                AbstractStandaloneHelper::class
            ));
        }

        parent::__construct($data, $override, $strict);

        $this->routing = $routing;
    }

    /**
     * {@inheritdoc}
     */
    protected function runCustomProcessOnContent(array $content)
    {
        $thumbnailUrl = null;
        $helperClass = self::STANDALONE_HELPER_CLASSNAME;
        if (null !== $thumbnailBaseDir = $this->getContentThumbnailBaseDir()) {
            $thumbnailFilepath = $thumbnailBaseDir . '/' . $content['type'] . '.svg';
            if (file_exists($thumbnailFilepath)) {
                $thumbnailUrl = $this->routing->getUri(str_replace(
                    ['//', $helperClass::rootDir() . '/web'],
                    ['/', ''],
                    $thumbnailFilepath
                ));
            }
        }

        $content['thumbnail'] = $thumbnailUrl;

        return $content;
    }

    /**
     * Gets content thumbnail base directory.
     *
     * @return string
     */
    private function getContentThumbnailBaseDir()
    {
        $helperClass = self::STANDALONE_HELPER_CLASSNAME;

        return realpath($helperClass::rootDir() . '/web/static/img/contents/');
    }
}
