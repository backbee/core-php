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

namespace BackBeeCloud\Entity;

use BackBee\AutoLoader\Exception\ClassNotFoundException;
use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\ClassContentManager;
use BackBee\ClassContent\ContentSet;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Exception\RevisionConflictedException;
use BackBee\ClassContent\Exception\RevisionMissingException;
use BackBee\ClassContent\Exception\RevisionUptodateException;
use BackBee\ClassContent\Exception\UnknownPropertyException;
use BackBee\ClassContent\Revision;
use BackBee\Event\Dispatcher;
use BackBee\NestedNode\Page;
use BackBee\Security\Token\BBUserToken;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

/**
 * Class ContentManager
 *
 * @package BackBeeCloud\Entity
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentManager
{
    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var ClassContentManager
     */
    protected $bbContentMgr;

    /**
     * @var Dispatcher
     */
    protected $eventDispatcher;

    /**
     * @var BBUserToken
     */
    protected $uniqToken;

    /**
     * @var BBApplication
     */
    protected $bbApp;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * ContentManager constructor.
     *
     * @param EntityManager       $entyMgr
     * @param ClassContentManager $bbContentMgr
     * @param Dispatcher          $eventDispatcher
     * @param BBApplication       $bbApp
     * @param LoggerInterface     $logger
     */
    public function __construct(
        EntityManager $entyMgr,
        ClassContentManager $bbContentMgr,
        Dispatcher $eventDispatcher,
        BBApplication $bbApp,
        LoggerInterface $logger
    ) {
        $this->entyMgr = $entyMgr;
        $this->bbContentMgr = $bbContentMgr;
        $this->eventDispatcher = $eventDispatcher;
        $this->uniqToken = $this->entyMgr->getRepository(Revision::class)->getUniqToken();
        $this->bbApp = $bbApp;
        $this->logger = $logger;
    }

    /**
     * Duplicates the provided content. Takes the drafted state if bbtoken is provided.
     *
     * @param AbstractClassContent $original
     * @param BBUserToken|null     $token
     * @param null                 $uid
     * @param bool                 $putOnline
     *
     * @return AbstractClassContent
     * @throws ClassNotFoundException
     * @throws UnknownPropertyException
     * @throws OptimisticLockException
     */
    public function duplicateContent(
        AbstractClassContent $original,
        BBUserToken $token = null,
        $uid = null,
        bool $putOnline = false
    ) {
        $draft = $token
            ? $this->entyMgr->getRepository(Revision::class)->getDraft($original, $token)
            : null;
        $original->setDraft($draft ?: $original->getDraft());

        $classname = AbstractClassContent::getClassnameByContentType($original->getContentType());
        $copy = new $classname($uid);

        foreach ($original->getData() as $key => $value) {
            $newVal = $value;
            if ($value instanceof AbstractClassContent) {
                $newVal = $this->duplicateContent($value, $token, null, $putOnline);
            } elseif (is_array($value)) {
                $newVal = [];
                foreach ($value as $subValue) {
                    if ($subValue instanceof AbstractClassContent) {
                        $newVal[] = $this->duplicateContent($subValue, $token, null, $putOnline);
                    }
                }
            }

            if ($copy->hasElement($key)) {
                $copy->$key = $newVal;
            } elseif ($newVal instanceof AbstractClassContent && $copy instanceof ContentSet) {
                $copy->push($newVal);
            }
        }

        foreach (array_keys($original->getDefaultParams()) as $key) {
            $copy->setParam($key, $original->getParamValue($key));
        }

        $this->eventDispatcher->dispatch('content.duplicate.presave', new ContentDuplicatePreSaveEvent($copy));
        $this->hydrateDraft($copy, $token);
        if (true === $putOnline) {
            $copy->setState(AbstractClassContent::STATE_NORMAL);
        }

        $this->entyMgr->persist($copy);
        $this->entyMgr->flush($copy);

        return $copy;
    }

    /**
     * Marks the given content as global.
     *
     * @param AbstractClassContent $content
     *
     * @return self
     * @throws OptimisticLockException
     */
    public function addGlobalContent(AbstractClassContent $content): ContentManager
    {
        $globalContent = $this->entyMgr->getRepository(GlobalContent::class)->findOneBy(['content' => $content]);
        if (null === $globalContent) {
            $globalContent = new GlobalContent($content);
            $this->entyMgr->persist($globalContent);
            $this->entyMgr->flush($globalContent);
        }

        return $this;
    }

    /**
     * Returns true if the given page has at least one drafted content.
     *
     * @param Page        $page
     * @param BBUserToken $token
     *
     * @return bool
     */
    public function isDraftedPage(Page $page, BBUserToken $token): bool
    {
        return !empty($this->getPageContentDrafts($page, $token));
    }

    /**
     * Hydrates a draft to the given content if an instance of BBUserToken is provided.
     *
     * This method will checkout a new draft if no draft is found.
     *
     * @param AbstractClassContent $content
     * @param BBUserToken|null     $token
     *
     * @return AbstractClassContent
     */
    public function hydrateDraft(AbstractClassContent $content, BBUserToken $token = null): AbstractClassContent
    {
        if (null !== $token) {
            $draft = $this->entyMgr->getRepository(Revision::class)->checkout($content, $token);
            $content->setDraft($draft);
        }

        return $content;
    }

    /**
     * Returns all contents drafts of the provided page.
     *
     * @param Page        $page
     * @param BBUserToken $token
     *
     * @return array
     */
    public function getPageContentDrafts(Page $page, BBUserToken $token): array
    {
        $contents = [];

        try {
            $contents = $this->entyMgr->getRepository(Revision::class)->findBy(
                [
                    '_owner' => UserSecurityIdentity::fromToken($this->uniqToken),
                    '_state' => [Revision::STATE_ADDED, Revision::STATE_MODIFIED, Revision::STATE_TO_DELETE],
                    '_content' => array_merge(
                        $this->getGlobalContentsUids(),
                        $this->getUidsFromPage($page, $token)
                    ),
                ]
            );
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $contents;
    }

    /**
     * Allows to commit only draft content that are located on the provided page.
     *
     * Returns the number of commited contents.
     *
     * @param Page        $page
     * @param BBUserToken $token
     *
     * @return int
     * @throws OptimisticLockException
     * @throws RevisionConflictedException
     * @throws RevisionMissingException
     * @throws RevisionUptodateException
     */
    public function publishByPage(Page $page, BBUserToken $token): int
    {
        $this->entyMgr->beginTransaction();
        foreach ($this->getDraftStageToDelete($page, $token) as $draft) {
            $content = $draft->getContent();
            $content->setDraft();
            if ($content instanceof ContentSet) {
                $content->clear();
            }

            $classname = AbstractClassContent::getClassnameByContentType($content->getContentType());
            $this->entyMgr->getRepository($classname)->deleteContent($content);
        }

        $this->entyMgr->flush();

        $commitedCount = 0;
        foreach ($this->getPageContentDrafts($page, $token) as $draft) {
            $content = $draft->getContent();

            $data = $draft->jsonSerialize();
            $result = [
                'elements' => [],
                'parameters' => [],
            ];
            if ($content instanceof ContentSet) {
                $result['elements'] = !($data['elements']['current'] === $data['elements']['draft']);
            } else {
                foreach ($data['elements'] as $attr => $stateData) {
                    if ($stateData['current'] !== $stateData['draft']) {
                        $result['elements'][] = $attr;
                    }
                }
            }

            foreach ($data['parameters'] as $attr => $stateData) {
                if ($stateData['current'] !== $stateData['draft']) {
                    $result['parameters'][] = $attr;
                }
            }

            $commitedCount++;
            if (false === $result['elements'] && false === $result['parameters']) {
                $content->setDraft($draft);
                $content->prepareCommitDraft();

                continue;
            }

            $content->setDraft();

            try {
                $this->bbContentMgr->commit($content, $result);
            } catch (Exception $e) {
            }
        }

        $this->entyMgr->flush();

        $qb = $this->entyMgr->createQueryBuilder();
        $result = $qb
            ->update(AbstractClassContent::class, 'c')
            ->set('c._state', AbstractClassContent::STATE_NORMAL)
            ->where($qb->expr()->in('c._uid', $this->getUidsFromPage($page, $token)))
            ->getQuery()
            ->execute();
        $commitedCount += $result;

        // commit transaction
        $this->entyMgr->commit();

        return $commitedCount;
    }

    /**
     * Reset by page.
     *
     * @param Page        $page
     * @param BBUserToken $token
     *
     * @return int
     * @throws OptimisticLockException
     */
    public function resetByPage(Page $page, BBUserToken $token): int
    {
        $this->entyMgr->beginTransaction();

        $cancelCount = 0;

        $drafts = array_merge(
            $this->getDraftStageToDelete($page, $token),
            $this->getPageContentDrafts($page, $token)
        );
        foreach ($drafts as $draft) {
            ++$cancelCount;
            $content = $draft->getContent();
            $content->setDraft();
            if (
                !$page->isOnline()
                && ($page->getCreated()->getTimestamp() + 3) > $content->getCreated()->getTimestamp()
            ) {
                $this->entyMgr->remove($draft);

                continue;
            }

            if (AbstractClassContent::STATE_NEW === $content->getState()) {
                if ($content instanceof ContentSet) {
                    $content->clear();
                }

                $classname = AbstractClassContent::getClassnameByContentType($content->getContentType());
                $this->entyMgr->getRepository($classname)->deleteContent($content);
            } else {
                $this->entyMgr->remove($draft);
            }
        }

        $this->entyMgr->flush();

        // commit transaction
        $this->entyMgr->commit();

        return $cancelCount;
    }

    /**
     * Returns uids of every content on the given page. You must provide a token
     * to also get uids of drafted contents.
     *
     * @param Page             $page
     * @param BBUserToken|null $token
     *
     * @return array
     */
    public function getUidsFromPage(Page $page, BBUserToken $token = null): array
    {
        $contents = [];

        try {
            $contents = $this->gatherContentsUids($page->getContentSet(), $token);
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $contents;
    }

    /**
     * Returns true if there is at least one global content with active draft (state added, modified or
     * to delete), else false.
     *
     * @return bool
     */
    public function hasGlobalContentDraft(): bool
    {
        $revisions = false;

        try {
            $revisions = $this->entyMgr->getRepository(Revision::class)->findOneBy(
                [
                    '_content' => $this->getGlobalContentsUids(),
                    '_state' => [Revision::STATE_ADDED, Revision::STATE_MODIFIED, Revision::STATE_TO_DELETE],
                ]
            );
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

        return null !== $revisions;
    }

    /**
     * Gathers recursively uids of every content and subcontent of the provided content.
     *
     * @param AbstractClassContent $content
     * @param BBUserToken|null     $token
     *
     * @return array
     * @throws ClassNotFoundException
     * @throws UnknownPropertyException
     */
    protected function gatherContentsUids(AbstractClassContent $content, BBUserToken $token = null): array
    {
        $uids = [];
        $resetDraft = null !== $content->getDraft();
        if (null !== $token && !$resetDraft) {
            $draft = $this->entyMgr->getRepository(Revision::class)->getDraft($content, $token);
            $content->setDraft($draft);
        }

        foreach ($content->getData() as $element) {
            if ($element instanceof AbstractClassContent) {
                $uids[] = $element->getUid();
                $uids = array_merge($uids, $this->gatherContentsUids($element, $token));
            } elseif (is_array($element)) {
                foreach ($element as $subelement) {
                    if ($subelement instanceof AbstractClassContent) {
                        $uids = array_merge($uids, $this->gatherContentsUids($subelement, $token));
                    }
                }
            }
        }

        $uids[] = $content->getUid();
        if (!$resetDraft) {
            $content->setDraft();
        }

        return array_unique($uids);
    }

    /**
     * Returns uids of global contents.
     *
     * @return array
     * @throws ClassNotFoundException
     * @throws UnknownPropertyException
     */
    protected function getGlobalContentsUids(): ?array
    {
        return array_merge(
            ...array_filter(
                array_map(
                    static function (GlobalContent $globalcontent) {
                        $uids = [];
                        if (null === $content = $globalcontent->getContent()) {
                            return $uids;
                        }

                        $uids[] = $content->getUid();
                        foreach ($content->getData() as $element) {
                            if ($element instanceof AbstractClassContent) {
                                $uids[] = $element->getUid();
                            }
                        }

                        return $uids;
                    },
                    $this->entyMgr->getRepository(GlobalContent::class)->findAll()
                )
            )
        );
    }

    /**
     * @param Page        $page
     * @param BBUserToken $token
     *
     * @return array|Revision[]
     */
    protected function getDraftStageToDelete(Page $page, BBUserToken $token): array
    {
        return $this->entyMgr->getRepository(Revision::class)->findBy(
            [
                '_state' => Revision::STATE_TO_DELETE,
                '_owner' => UserSecurityIdentity::fromToken($this->uniqToken),
                '_content' => $this->getUidsFromPage($page, $token),
            ]
        );
    }

    /**
     * Get all image for an page with id and path for each image.
     *
     * @param Page $page
     *
     * @return array
     */
    public function getAllImageForAnPage(Page $page): array
    {
        $entries = [];

        try {
            $images = $this->entyMgr->getRepository(Image::class)->findBy(['_uid' => $this->getUidsFromPage($page)]);

            if (false === empty($images)) {
                foreach ($images as $image) {
                    if (null !== $image->image->path) {
                        $entries[] = [
                            'uid' => $image->image->getUid(),
                            'original_name' => $image->image->originalname,
                            'path' => $image->image->path,
                            'alt' => $image->getParamValue('alt')
                        ];
                    }
                }
            }
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $entries;
    }
}
