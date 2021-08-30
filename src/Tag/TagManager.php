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

namespace BackBeeCloud\Tag;

use BackBee\NestedNode\KeyWord as Tag;
use BackBee\NestedNode\Page;
use BackBee\NestedNode\Repository\KeyWordRepository as TagRepository;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Entity\Lang;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Class TagManager
 *
 * @package BackBeeCloud\Entity
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class TagManager
{
    public const DEFAULT_LIMIT = 15;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var ElasticsearchManager
     */
    protected $elasticsearchManager;

    /**
     * @var TagRepository
     */
    protected $repository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * TagManager constructor.
     *
     * @param EntityManager        $entityManager
     * @param ElasticsearchManager $elasticsearchManager
     * @param LoggerInterface      $logger
     */
    public function __construct(
        EntityManager $entityManager,
        ElasticsearchManager $elasticsearchManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->elasticsearchManager = $elasticsearchManager;
        $this->repository = $this->entityManager->getRepository(Tag::class);
        $this->logger = $logger;
    }

    /**
     * Creates the tag entity if it does not exist yet and indexes it into
     * Elasticsearch.
     *
     * @param string     $tag
     * @param Tag|null   $parent
     * @param array|null $translations
     *
     * @return Tag
     */
    public function create(string $tag, ?Tag $parent = null, ?array $translations = null): Tag
    {
        if ($this->repository->exists(ucfirst($tag))) {
            throw new RuntimeException(
                sprintf(
                    'Cannot create tag (:%s) because it already exists.',
                    $tag
                )
            );
        }

        $tagEntity = new Tag();
        $tagEntity->setRoot($this->getRootTag());
        $tagEntity->setKeyWord(preg_replace('#[/\"]#', '', trim($tag)));

        if ($parent) {
            $tagEntity->setParent($parent);
        }

        try {
            $this->entityManager->persist($tagEntity);
            if ($translations && array_filter($translations)) {
                $this->handleTagTranslations($tagEntity, $translations);
            }
            $this->entityManager->flush();

        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        $this->elasticsearchManager->indexTag($tagEntity);

        return $tagEntity;
    }

    /**
     * Create tag if not exists.
     *
     * @param            $tagName
     * @param Tag|null   $parent
     * @param array|null $translations
     *
     * @return Tag
     */
    public function createIfNotExists($tagName, ?Tag $parent = null, ?array $translations = null): Tag
    {
        $tag = null;

        try {
            $tag = $this->create($tagName, $parent, $translations);
        } catch (RuntimeException $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
            $tag = $this->entityManager->getRepository(Tag::class)->exists($tagName);
        }

        return $tag;
    }

    /**
     * Handles the whole process of updating a tag with a new name. This method
     * ensures the integrity of the database and Elasticseach indices.
     *
     * @param Tag        $tag
     * @param string     $newName
     * @param Tag|null   $parent
     * @param array|null $translations
     *
     * @return self
     */
    public function update(Tag $tag, string $newName, ?Tag $parent = null, ?array $translations = null): TagManager
    {
        $this->entityManager->beginTransaction();

        if ($tag === $parent) {
            throw new RuntimeException(
                'A tag\'s parent cannot be a self reference.'
            );
        }

        try {
            if (null === $parent && null !== $tag->getParent()) {
                $this->resetTagParent($tag);
            } elseif ($parent) {
                $parentParent = $parent->getParent();
                while ($parentParent) {
                    if ($parentParent === $tag) {
                        throw new RuntimeException(
                            'A tag cannot have one of his children as parent.'
                        );
                    }
                    $parentParent = $parentParent->getParent();
                }

                $tag->setParent($parent);
                $this->entityManager->flush($tag);
            }

            if ($translations) {
                $this->handleTagTranslations($tag, $translations);
                $this->entityManager->flush();
            }

            if (strtolower($tag->getKeyWord()) === strtolower($newName)) {
                if ($tag->getKeyWord() !== $newName) {
                    $tag->setKeyWord($newName);
                    $this->entityManager->flush($tag);
                    $this->entityManager->commit();

                    $this->elasticsearchManager->indexTag($tag);
                } else {
                    $this->entityManager->commit();
                }

                return $this;
            }

        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }


        $linkedPages = $this->getLinkedPages($tag);
        $newTag = $this->entityManager->getRepository(Tag::class)->exists($newName);
        if ($newTag) {
            throw new RuntimeException(
                sprintf(
                    'cannot rename tag to "%s" because tag with this name already exists.',
                    $newName
                )
            );
        }

        try {
            $tag->setKeyWord($newName);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        $this->elasticsearchManager->indexTag($tag);

        try {
            foreach ($linkedPages as $row) {
                $this->elasticsearchManager->indexPage(
                    $this->entityManager->find(
                        Page::class,
                        $row['id']
                    )
                );
            }
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        return $this;
    }

    /**
     * Deletes the given tag and also ensure to delete the tag from all pages.
     *
     * @param Tag $tag
     *
     * @return self
     */
    public function delete(Tag $tag): TagManager
    {
        $linkedPages = $this->getLinkedPages($tag);

        try {
            $this->entityManager->getConnection()->executeUpdate(
                'DELETE FROM page_tag_keyword WHERE tag_uid = :tag_uid',
                [
                    'tag_uid' => $tag->getUid(),
                ]
            );

            $this->entityManager->remove($tag);
            $this->entityManager->flush($tag);
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        try {
            foreach ($linkedPages as $row) {
                $this->elasticsearchManager->indexPage($this->entityManager->find(Page::class, $row['id']));
            }
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        $this->elasticsearchManager->deleteTag($tag);

        return $this;
    }

    /**
     * Returns an array (id; title) of pages that contain the provided tag.
     *
     * @param Tag $tag
     *
     * @return array
     */
    public function getLinkedPages(Tag $tag): array
    {
        $pages = [];

        try {
            $result = $this->entityManager
                ->getConnection()
                ->executeQuery(
                    'SELECT pt.page_uid FROM page_tag pt LEFT JOIN page_tag_keyword ptk ON ptk.page_tag_id = pt.id WHERE ptk.tag_uid = :tag_uid',
                    ['tag_uid' => $tag->getUid()]
                )->fetchAll();

            foreach (array_column($result, 'page_uid') as $pageUid) {
                $item = $this->elasticsearchManager->getClient()->get(
                    [
                        'id' => $pageUid,
                        'index' => $this->elasticsearchManager->getIndexName(),
                    ]
                );
                $pages[] = [
                    'id' => $item['_id'],
                    'title' => $item['_source']['title'],
                ];
            }
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        return $pages;
    }

    /**
     * Get by.
     *
     * @param string $prefix
     * @param int    $start
     * @param int    $limit
     *
     * @return ElasticsearchCollection
     */
    public function getBy(
        string $prefix = '',
        int $start = 0,
        int $limit = self::DEFAULT_LIMIT
    ): ElasticsearchCollection {
        return $this->elasticsearchManager->searchTag(
            preg_replace('#[/\"]#', '', trim($prefix)),
            $start,
            $limit
        );
    }

    /**
     * Get tree first level tags.
     *
     * @param int $start
     * @param int $limit
     *
     * @return array
     */
    public function getTreeFirstLevelTags(int $start = 0, int $limit = self::DEFAULT_LIMIT): array
    {
        $result = [];

        $qb = $this->entityManager->getRepository(Tag::class)->createQueryBuilder('t');

        try {
            $result['max_count'] = (int)$qb
                ->select('count(t)')
                ->where(
                    $qb->expr()->andX(
                        $qb->expr()->neq('t._keyWord', ':root_keyword'),
                        $qb->expr()->isNull('t._parent')
                    )
                )
                ->setParameter('root_keyword', 'root')
                ->getQuery()
                ->getSingleScalarResult();

            $result['collection'] = $qb
                ->select('t')
                ->setFirstResult($start)
                ->setMaxResults($limit)
                ->orderBy('t._keyWord', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        return $result;
    }

    /**
     * Get tag entity.
     *
     * @param $uid
     *
     * @return Tag|object|null
     */
    public function get($uid)
    {
        return $this->repository->find($uid);
    }

    /**
     * Get root tag.
     *
     * @return Tag|null
     */
    private function getRootTag(): ?Tag
    {
        return $this->repository->find(md5('root'));
    }

    /**
     * Reset tag parent.
     *
     * @param Tag $tag
     */
    private function resetTagParent(Tag $tag): void
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb
            ->update(Tag::class, 't')
            ->set('t._parent', ':null')
            ->setParameter('null', null)
            ->where(
                $qb->expr()->eq('t', ':tag')
            )
            ->setParameter('tag', $tag)
            ->getQuery()
            ->execute();
    }

    /**
     * Handle tag translations.
     *
     * @param Tag   $tag
     * @param array $translations
     */
    private function handleTagTranslations(Tag $tag, array $translations): void
    {
        try {
            foreach ($translations as $lang => $translation) {
                if (null === $lang = $this->entityManager->find(Lang::class, $lang)) {
                    continue;
                }

                $tagLang = $this->getTagLangByTagAndLang($tag, $lang);
                if (null === $tagLang && false === $translation) {
                    continue;
                }

                if ($tagLang && false === $translation) {
                    $this->entityManager->remove($tagLang);

                    continue;
                }

                if (null === $tagLang) {
                    $tagLang = new TagLang($tag, $lang, $translation);
                    $this->entityManager->persist($tagLang);
                }

                $tagLang->setTranslation($translation);
            }
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }
    }

    /**
     * Get tag lang by tag tag and lang.
     *
     * @param Tag  $tag
     * @param Lang $lang
     *
     * @return TagLang|object|null
     */
    private function getTagLangByTagAndLang(Tag $tag, Lang $lang)
    {
        return $this->entityManager->getRepository(TagLang::class)->findOneBy(compact('tag', 'lang'));
    }

    /**
     * Get tags value.
     *
     * @param array $tags
     * @param bool  $withChildren
     *
     * @return array
     */
    public function getTagsValue(array $tags, bool $withChildren = false): array
    {
        $validTags = [];

        try {
            foreach ($tags as $data) {
                if (isset($data['uid'], $data['label']) && $tag = $this->repository->find($data['uid'])) {
                    $validTags[] = $tag->getKeyWord();
                    if ($withChildren) {
                        $validTags = $this->getTagWithAllChildren($tag, $validTags);
                    }
                } elseif ($this->repository->exists($data)) {
                    $validTags[] = $data;
                }
            }
        } catch (Exception $exception) {
            $this->logger->error(sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }

        return $validTags;
    }


    /**
     * Get tag with all children.
     *
     * @param Tag   $tag
     * @param array $values
     *
     * @return array
     */
    private function getTagWithAllChildren(Tag $tag, array $values): array
    {
        $children = [];

        foreach ($tag->getChildren()->toArray() as $child) {
            if ($child->getChildren()->toArray()) {
                $children = $this->getTagWithAllChildren($child, $values);
            }

            $values[] = $child->getKeyWord();
        }

        return array_values(array_unique(array_merge($values, $children)));
    }
}
