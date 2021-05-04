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

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractContent;
use BackBee\ClassContent\ClassContentManager;
use BackBee\ClassContent\Revision;
use BackBee\NestedNode\Page;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Entity\PageManager;
use DateTime;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentController extends AbstractController
{
    /**
     * @var ClassContentManager
     */
    protected $contentMgr;

    /**
     * @var ElasticsearchManager
     */
    protected $elasticsearchMgr;

    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var PageManager
     */
    protected $pageMgr;

    /**
     * @var array
     */
    protected $contentsUids = [];

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->contentMgr = $app->getContainer()->get('cloud.content_manager');
        $this->elasticsearchMgr = $app->getContainer()->get('elasticsearch.manager');
        $this->entyMgr = $app->getEntityManager();
        $this->pageMgr = $app->getContainer()->get('cloud.page_manager');
    }

    public function delete($type, $uid)
    {
        $this->assertIsAuthenticated();

        $classname = AbstractContent::getClassnameByContentType($type);
        $content = $this->entyMgr->find($classname, $uid);
        if (null === $content) {
            return new JsonResponse(
                [
                    'error' => 'not_found',
                    'reason' => "Content with uid `{$uid}` does not exist.",
                ], Response::HTTP_NOT_FOUND
            );
        }

        $this->entyMgr->beginTransaction();
        $draft = $this->entyMgr->getRepository(Revision::class)->getDraft(
            $content,
            $this->securityContext->getToken(),
            true
        );
        $draft->setState(Revision::STATE_TO_DELETE);
        $this->entyMgr->flush();
        $this->entyMgr->commit();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function commitPage($pageuid)
    {
        $this->assertIsAuthenticated();

        $page = $this->entyMgr->find(Page::class, $pageuid);
        if (false === $page) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        return new Response(
            '', Response::HTTP_NO_CONTENT, [
            'X-Published-Count' => $this->runCommitPage($page),
        ]
        );
    }

    public function getPagesToCommit()
    {
        $this->assertIsAuthenticated();

        $result = [];
        foreach ($this->pageMgr->getPagesWithDraftContents() as $page) {
            $result[] = [
                'uid' => $page->getUid(),
                'title' => $page->getTitle(),
            ];
        }

        $max = count($result);
        $end = $max - 1;

        return new JsonResponse(
            $result, Response::HTTP_OK, [
            'Content-Range' => $max ? "0-$end/$max" : '-/-',
        ]
        );
    }

    public function reset($pageuid)
    {
        $this->assertIsAuthenticated();

        $page = $this->entyMgr->find('BackBee\NestedNode\Page', $pageuid);
        if (false === $page) {
            return $this->getPageNotFoundResponse($pageuid);
        }

        $count = $this->contentMgr->resetByPage($page, $this->securityContext->getToken());
        $this->elasticsearchMgr->indexPage($page);

        return new Response(
            '', Response::HTTP_NO_CONTENT, [
            'X-Rollback-Count' => $count,
        ]
        );
    }

    protected function getPageNotFoundResponse($pageuid)
    {
        return new JsonResponse(
            [
                'error' => 'not_found',
                'reason' => "Page with uid `{$pageuid}` does not exist.",
            ], Response::HTTP_NOT_FOUND
        );
    }

    protected function runCommitPage(Page $page)
    {
        $commitedCount = $this->contentMgr->publishByPage($page, $this->securityContext->getToken());

        if (0 < $commitedCount) {
            $page->setModified(new DateTime());
        }

        $this->entyMgr->flush($page);
        $this->elasticsearchMgr->indexPage($page);

        return $commitedCount;
    }
}
