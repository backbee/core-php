<?php

namespace BackBee\Listener\Log;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Revision;
use BackBee\Event\Event;

/**
 * Class ClassContentLogListener
 *
 * @package BackBee\Listener
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ClassContentLogListener extends AbstractLogListener implements LogListenerInterface
{
    /**
     * On flush content.
     *
     * @param Event $event
     */
    public static function onFlushContent(Event $event): void
    {
        $content = $event->getTarget();

        if ($content instanceof AbstractClassContent) {
            self::writeLog($content, self::getBeforeContent($content), self::getAfterContent($content));
        }
    }

    /**
     * On pre remove content.
     *
     * @param Event $event
     */
    public static function onPreRemoveContent(Event $event): void
    {

    }

    /**
     * Get before content.
     *
     * @param AbstractClassContent $content
     *
     * @return null|array
     */
    private static function getBeforeContent(AbstractClassContent $content): ?array
    {
        $id = $content->getRevision();
        $revision = self::$entityManager->getRepository(Revision::class)->findOneBy(['_revision' => $id - 1]);

        return [
            'content' => $revision ? $revision->getData() : null,
            'parameter' => $revision ? $revision->getAllParams() : null,
        ];
    }

    /**
     * Get after content.
     *
     * @param AbstractClassContent $content
     *
     * @return array|null
     */
    private static function getAfterContent(AbstractClassContent $content): ?array
    {
        $isScheduledForDelete = self::$entityManager->getUnitOfWork()->isScheduledForDelete($content);

        return [
            'content' => $isScheduledForDelete ? null : $content->getDataToObject(),
            'parameter' => $isScheduledForDelete ? null : $content->getAllParams(),
        ];
    }
}