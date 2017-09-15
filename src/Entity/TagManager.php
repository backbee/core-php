<?php

namespace BackBeeCloud\Entity;

use BackBee\NestedNode\Builder\KeywordBuilder;
use BackBee\NestedNode\KeyWord as Tag;
use Doctrine\ORM\EntityManager;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TagManager
{
    const DEFAULT_LIMIT = 15;

    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var ElasticsearchManager
     */
    protected $elasticMgr;

    /**
     * @var KeywordBuilder
     */
    protected $kwbuilder;

    public function __construct(EntityManager $entyMgr, ElasticsearchManager $elasticMgr)
    {
        $this->entyMgr = $entyMgr;
        $this->elasticMgr = $elasticMgr;
        $this->kwbuilder = new KeywordBuilder($this->entyMgr);
    }

    /**
     * Creates the tag entity if it does not exist yet and indexes it into
     * Elasticsearch.
     *
     * @param  string $tag
     * @return Tag
     */
    public function create($tag)
    {
        $tag = $this->kwbuilder->createKeywordIfNotExists(ucfirst($tag));
        $this->elasticMgr->indexTag($tag);

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
    public function update(Tag $tag, $newName)
    {
        if (strtolower($tag->getKeyWord()) === strtolower($newName)) {
            return $this;
        }

        $elasticsearchMethod = 'indexTag';
        $pages = $this->getLinkedPages($tag);
        $newTag = $this->entyMgr->getRepository(Tag::class)->exists($newName);
        if (null === $newTag) {
            $tag->setKeyWord($newName);
        } else {
            $result = $this->entyMgr->getConnection()->executeQuery(sprintf(
                'SELECT id FROM page_tag WHERE page_uid IN (%s)',
                '"' . implode('", "', array_column($pages, 'uid')) . '"'
            ))->fetchAll();

            $inClause = '"' . implode('", "', array_column($result, 'id')) . '"';
            $this->entyMgr->getConnection()->executeUpdate(
                "DELETE FROM page_tag_keyword WHERE page_tag_id IN ({$inClause}) AND tag_uid = :new_tag_uid",
                ['new_tag_uid' => $newTag->getUid()]
            );

            $this->entyMgr->getConnection()->executeUpdate(
                'UPDATE page_tag_keyword SET tag_uid = :new_tag_uid WHERE tag_uid = :old_tag_uid',
                [
                    'new_tag_uid' => $newTag->getUid(),
                    'old_tag_uid' => $tag->getUid(),
                ]
            );
            $this->entyMgr->remove($tag);
            $elasticsearchMethod = 'deleteTag';
        }

        $this->entyMgr->flush($tag);
        $this->elasticMgr->$elasticsearchMethod($tag);
        foreach ($pages as $row) {
            $this->elasticMgr->indexPage($this->entyMgr->find('BackBee\NestedNode\Page', $row['id']));
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
        $pages = $this->getLinkedPages($tag);
        $this->entyMgr->getConnection()->executeUpdate(
            'DELETE FROM page_tag_keyword WHERE tag_uid = :tag_uid',
            [ 'tag_uid' => $tag->getUid() ]
        );

        $this->entyMgr->remove($tag);
        $this->entyMgr->flush($tag);
        foreach ($pages as $row) {
            $this->elasticMgr->indexPage($this->entyMgr->find('BackBee\NestedNode\Page', $row['id']));
        }

        $this->elasticMgr->deleteTag($tag);

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
        $result = $this->entyMgr->getConnection()->executeQuery(
            'SELECT pt.page_uid
             FROM page_tag pt
             LEFT JOIN page_tag_keyword ptk ON ptk.page_tag_id = pt.id
             WHERE ptk.tag_uid = :tag_uid',
            [ 'tag_uid' => $tag->getUid() ]
        )->fetchAll();

        $pages = [];
        foreach (array_column($result, 'page_uid') as $pageUid) {
            $item = $this->elasticMgr->getClient()->get([
                'id'    => $pageUid,
                'index' => $this->elasticMgr->getIndexName(),
                'type'  => $this->elasticMgr->getPageTypeName(),
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
        return $this->elasticMgr->searchTag(
            preg_replace('#[/\"]#', '', trim($prefix)),
            $start,
            $limit
        );
    }

    public function get($uid)
    {
        return $this->entyMgr->find(Tag::class, $uid);
    }
}
