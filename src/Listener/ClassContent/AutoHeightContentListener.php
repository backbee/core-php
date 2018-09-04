<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
use BackBee\ClassContent\Media\Map;
use BackBee\ClassContent\Repository\RevisionRepository;
use BackBee\ClassContent\Revision;
use BackBee\Renderer\Event\RendererEvent;
use BackBee\Security\Token\BBUserToken;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class AutoHeightContentListener
{
    /**
     * @var BBUserToken
     */
    private $bbtoken;

    /**
     * @var RevisionRepository
     */
    private $revisionRepository;

    public function __construct(BBApplication $app)
    {
        $this->bbtoken = $app->getBBUserToken();
        $this->revisionRepository = $app->getEntityManager()->getRepository(Revision::class);
    }

    public function onCloudContentSetRender(RendererEvent $event)
    {
        $content = $event->getTarget();
        if (!($content instanceof CloudContentSet)) {
            return;
        }

        if (1 === count($content->getData())) {
            return;
        }

        foreach ($content->getData() as $colContentSet) {
            if (
                $colContentSet instanceof ColContentSet
                && 1 === count($colContentSet->getData())
                && $colContentSet->getData()[0] instanceof AbstractClassContent
                && self::isContentAutoHeightEnabled($colContentSet->getData()[0])
            ) {
                $currentValue = (string) $event->getRenderer()->row_extra_css_classes;
                $currentValue = $currentValue . ' auto-height';
                $event->getRenderer()->assign('row_extra_css_classes', $currentValue);

                return;
            }
        }
    }

    protected function isContentAutoHeightEnabled(AbstractClassContent $content)
    {
        $paramKeys = array_keys($content->getAllParams());

        if (null !== $this->bbtoken && null === $content->getDraft()) {
            $content->setDraft($this->revisionRepository->getDraft($content, $this->bbtoken));
        }

        return
            ($content instanceof Image || $content instanceof Map)
            && in_array('force_auto_height', $paramKeys)
            && true === $content->getParamValue('force_auto_height')
        ;
    }
}
