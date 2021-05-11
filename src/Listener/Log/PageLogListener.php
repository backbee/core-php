<?php

namespace BackBee\Listener\Log;

use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Security\SecurityContext;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Entity\PageManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PageLogListener
 *
 * @package BackBee\Listener\Log
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageLogListener extends AbstractLogListener implements LogListenerInterface
{
    /**
     * @var PageManager
     */
    private static $pageManager;

    /**
     * @var ElasticsearchManager
     */
    private static $elasticsearchManager;

    /**
     * PageLogListener constructor.
     *
     * @param SecurityContext        $context
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface|null   $logger
     * @param PageManager            $pageManager
     * @param ElasticsearchManager   $elasticsearchManager
     */
    public function __construct(
        SecurityContext $context,
        EntityManagerInterface $entityManager,
        ?LoggerInterface $logger,
        PageManager $pageManager,
        ElasticsearchManager $elasticsearchManager
    ) {
        self::$pageManager = $pageManager;
        self::$elasticsearchManager = $elasticsearchManager;
        parent::__construct($context, $entityManager, $logger);
    }

    /**
     * {@inheritDoc}
     */
    public static function onFlush(Event $event): void
    {
        $page = $event->getTarget();

        if ($page instanceof Page) {
            self::writeLog(
                $page,
                self::getBeforeContent($page),
                self::getAfterContent($page)
            );
        }
    }

    /**
     * Get before content.
     *
     * @param Page $page
     *
     * @return null|array
     */
    private static function getBeforeContent(Page $page): ?array
    {
        $content = null;
        $isScheduledForUpdate = self::$entityManager->getUnitOfWork()->isScheduledForUpdate($page);

        if ($isScheduledForUpdate) {
            $data = self::$elasticsearchManager->getPageByUid($page->getUid());
            $content = [
                'id' => $page->getUid(),
                'title' => $data['title'],
//                'type' => $data['type'],
//                'category' => $data['category'],
//                'is_online' => $data['is_online'],
//                'is_drafted' => $data['is_drafted'],
//                'url' => $data['url'],
//                'tags' => $data['tags'],
            ];
        }

        return [
            'content' => $isScheduledForUpdate ? $content : null,
            'parameters' => $isScheduledForUpdate ? '' : null,
        ];
    }

    /**
     * Get after content.
     *
     * @param Page $page
     *
     * @return array|null
     */
    private static function getAfterContent(Page $page): ?array
    {
        $isScheduledForUpdate = self::$entityManager->getUnitOfWork()->isScheduledForUpdate($page);
        $isScheduledForInsert = self::$entityManager->getUnitOfWork()->isScheduledForInsert($page);

        if ($isScheduledForInsert) {
            self::$elasticsearchManager->indexPage($page);
        }

        $data = self::$pageManager->format($page);

        $content = [
            'id' => $data['id'],
            'title' => $data['title'],
            'type' => $data['type'],
            'category' => $data['category'],
            'is_online' => $data['is_online'],
            'is_drafted' => $data['is_drafted'],
            'url' => $data['url'],
            'tags' => $data['tags'],
        ];

        $parameters = [
            'seo' => $data['seo'],
        ];

        return [
            'content' => $isScheduledForInsert || $isScheduledForUpdate ? $content : null,
            'parameters' => $isScheduledForInsert || $isScheduledForUpdate ? $parameters : null,
        ];
    }
}
