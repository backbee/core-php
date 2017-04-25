<?php

namespace BackBeeCloud\Listener;

use BackBee\Rest\Controller\Event\ValidateFileUploadEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class FileUploadListener
{
    public static function onFileUploadEvent(ValidateFileUploadEvent $event)
    {
        if (false === exif_imagetype($event->getFilepath())) {
            $event->invalidateFile('Only images can be uploaded');
        }
    }
}
