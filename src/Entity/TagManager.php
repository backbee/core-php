<?php

namespace BackBeeCloud\Entity;

use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Tag\TagLang;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\NestedNode\Page;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TagManager
{
    const DEFAULT_LIMIT = 15;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var ElasticsearchManager
     */
    protected $elasticsearchManager;

    public function __construct(EntityManager $entityManager, ElasticsearchManager $elasticsearchManager)
    {
        $this->entityManager = $entityManager;
        $this->elasticsearchManager = $elasticsearchManager;
    }

    /**
     * Creates the tag entity if it does not exist yet and indexes it into
     * Elasticsearch.
     *
     * @param  string $tag
     * @return Tag
     */
    public function create($tag, Tag $parent = null, array $translations = null)
    {
        $repository = $this->entityManager->getRepository(Tag::class);
        if ($repository->exists(ucfirst($tag))) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot create tag (:%s) because it already exists.',
                    $tag
                )
            );
        }

        $tagEntity = new Tag();
        $tagEntity->setRoot($this->getRootTag());
        $tagEntity->setKeyWord(
            preg_replace(
                '#[/\"]#',
                '',
                trim($tag)
            )
        );

        if ($parent) {
            $tagEntity->setParent($parent);
        }

        $this->entityManager->persist($tagEntity);
        if ($translations) {
            $this->handleTagTranslations($tagEntity, $translations);
        }

        $this->entityManager->flush();

        $this->elasticsearchManager->indexTag($tagEntity);


        return $tagEntity;
    }

    public function createIfNotExists($tagName, Tag $parent = null, array $translations = null)
    {
        $tag = null;

        try {
            $tag = $this->create($tagName, $parent, $translations);
        } catch (\RuntimeException $exception) {
            $tag = $this->entityManager->getRepository(Tag::class)->exists($tagName);
        }

        return $tag;
    }

    /**
     * Handles the whole process of updating a tag with a new name. This method
     * ensures the integrity of the database and Elasticseach indices.
     *
     * @param  Tag    $tag
     * @param  string $newName
     * @return self
     */
    public function update(Tag $tag, $newName, Tag $parent = null, array $translations = null)
    {
        $this->entityManager->beginTransaction();

        if ($tag === $parent) {
            throw new \RuntimeException(
                'A tag\'s parent cannot be a self reference.'
            );
        }

        if (null === $parent && null !== $tag->getParent()) {
            $this->resetTagParent($tag);
        } elseif ($parent) {
            $parentParent = $parent->getParent();
            while ($parentParent) {
                if ($parentParent === $tag) {
                    throw new \RuntimeException(
                        'A tag cannot have one of his children as parent.'
                    );
                }
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

        $linkedPages = $this->getLinkedPages($tag);
        $newTag = $this->entityManager->getRepository(Tag::class)->exists($newName);
        if ($newTag) {
            throw new \RuntimeException(
                sprintf(
                    'cannot rename tag to "%s" because tag with this name already exists.',
                    $newName
                )
            );
        }

        $tag->setKeyWord($newName);

        $this->entityManager->flush();
        $this->entityManager->commit();

        $this->elasticsearchManager->indexTag($tag);
        foreach ($linkedPages as $row) {
            $this->elasticsearchManager->indexPage(
                $this->entityManager->find(
                    Page::class,
                    $row['id']
                )
            );
        }

        return $this;
    }

    /**
     * Deletes the given tag and also ensure to delete the tag from all pages.
     *
     * @param  Tag    $tag
     * @return self
     */
    public function delete(Tag $tag)
    {
        $linkedPages = $this->getLinkedPages($tag);
        $this->entityManager->getConnection()->executeUpdate(
            'DELETE FROM page_tag_keyword WHERE tag_uid = :tag_uid',
            ['tag_uid' => $tag->getUid()]
        );

        $this->entityManager->remove($tag);
        $this->entityManager->flush($tag);
        foreach ($linkedPages as $row) {
            $this->elasticsearchManager->indexPage($this->entityManager->find(Page::class, $row['id']));
        }

        $this->elasticsearchManager->deleteTag($tag);

        return $this;
    }

    /**
     * Returns an array (id; title) of pages that contain the provided tag.
     *
     * @param  Tag   $tag
     * @return array
     */
    public function getLinkedPages(Tag $tag)
    {
        $result = $this->entityManager->getConnection()->executeQuery(
            'SELECT pt.page_uid
             FROM page_tag pt
             LEFT JOIN page_tag_keyword ptk ON ptk.page_tag_id = pt.id
             WHERE ptk.tag_uid = :tag_uid',
            ['tag_uid' => $tag->getUid()]
        )->fetchAll();

        $pages = [];
        foreach (array_column($result, 'page_uid') as $pageUid) {
            $item = $this->elasticsearchManager->getClient()->get([
                'id'    => $pageUid,
                'index' => $this->elasticsearchManager->getIndexName(),
                'type'  => $this->elasticsearchManager->getPageTypeName(),
            ]);
            $pages[] = [
                'id'    => $item['_id'],
                'title' => $item['_source']['title'],
            ];
        }

        return $pages;
    }

    public function getBy($prefix = '', $start = 0, $limit = self::DEFAULT_LIMIT)
    {
        return $this->elasticsearchManager->searchTag(
            preg_replace('#[/\"]#', '', trim($prefix)),
            $start,
            $limit
        );
    }

    public function getTreeFirstLevelTags($start = 0, $limit = self::DEFAULT_LIMIT)
    {
        $qb = $this->entityManager->getRepository(Tag::class)->createQueryBuilder('t');

        $countMax = (int) $qb
            ->select('count(t)')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->neq('t._keyWord', ':root_keyword'),
                    $qb->expr()->isNull('t._parent')
                )
            )
            ->setParameter('root_keyword', 'root')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $collection = $qb
            ->select('t')
            ->setFirstResult($start)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return [
            'collection' => $collection,
            'max_count' => $countMax,
        ];
    }

    public function get($uid)
    {
        return $this->entityManager->find(Tag::class, $uid);
    }

    private function getRootTag()
    {
        return $this->entityManager->find(
            Tag::class,
            md5('root')
        );
    }

    private function resetTagParent(Tag $tag)
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
            ->execute()
        ;
    }

    private function handleTagTranslations(Tag $tag, array $translations)
    {
        foreach ($translations as $lang => $translation) {
            if (null === $lang = $this->entityManager->find(Lang::class, $lang)) {
                continue;
            }

            $tagLang = $this->getTagLangByTagAndLang($tag, $lang);
            if (null === $tagLang && false == $translation) {
                continue;
            }

            if ($tagLang && false == $translation) {
                $this->entityManager->remove($tagLang);

                continue;
            }

            if (null === $tagLang) {
                $tagLang = new TagLang($tag, $lang, $translation);
                $this->entityManager->persist($tagLang);
            }

            $tagLang->setTranslation($translation);
        }
    }

    private function getTagLangByTagAndLang(Tag $tag, Lang $lang)
    {
        return $this->entityManager->getRepository(TagLang::class)->findOneBy([
            'tag' => $tag,
            'lang' => $lang,
        ]);
    }
}
