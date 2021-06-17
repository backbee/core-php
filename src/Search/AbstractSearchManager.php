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

namespace BackBeeCloud\Search;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\NestedNode\Page;
use BackBeeCloud\Entity\ContentManager;
use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\PageType\TypeInterface;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractSearchManager
 *
 * @package BackBeeCloud\Search
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractSearchManager
{
    /**
     * @var ContentManager
     */
    protected $contentMgr;

    /**
     * @var PageManager
     */
    protected $pageMgr;

    /**
     * @var EntityManager
     */
    protected $entityMgr;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param PageManager     $pageMgr
     * @param ContentManager  $contentMgr
     * @param EntityManager   $entityMgr
     * @param LoggerInterface $logger
     */
    public function __construct(
        PageManager $pageMgr,
        ContentManager $contentMgr,
        EntityManager $entityMgr,
        LoggerInterface $logger
    ) {
        $this->pageMgr = $pageMgr;
        $this->contentMgr = $contentMgr;
        $this->entityMgr = $entityMgr;
        $this->logger = $logger;
    }

    /**
     * Returns the result page entity.
     *
     * @param string|null $lang
     *
     * @return null|Page
     */
    abstract public function getResultPage($lang = null): ?Page;

    /**
     * Builds and returns a result page according to provided uid, title and page type.
     *
     * Note that the created page's state will be online and its contents with normal
     * state (= online).
     *
     * @param string        $uid
     * @param string        $title
     * @param TypeInterface $type
     * @param string        $url
     * @param null|string   $lang
     *
     * @return Page
     */
    protected function buildResultPage(string $uid, string $title, TypeInterface $type, string $url, $lang = null): Page
    {
        if (null === $page = $this->pageMgr->get($uid)) {
            $this->entityMgr->beginTransaction();

            $data = [
                'uid' => $uid,
                'title' => $title,
                'type' => $type->uniqueName(),
            ];

            if ($lang) {
                $data['lang'] = $lang;
            }

            $page = $this->pageMgr->create($data);
            $page->setState(Page::STATE_ONLINE);
            $page->setUrl($url);

            try {
                foreach ($this->contentMgr->getUidsFromPage($page) as $contentUid) {
                    $content = $this->entityMgr->find(AbstractClassContent::class, $contentUid);
                    if ($content) {
                        $content->setRevision(1);
                        $content->setState(AbstractClassContent::STATE_NORMAL);
                    }
                }

                $this->entityMgr->flush();
                $this->entityMgr->commit();
            } catch (Exception $exception) {
                $this->logger->error(
                    sprintf(
                        '%s : %s :%s',
                        __CLASS__,
                        __FUNCTION__,
                        $exception->getMessage()
                    )
                );
            }
        }

        return $page;
    }
}
