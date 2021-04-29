<?php

namespace BackBeeCloud\Migration;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Image as BasicImage;
use BackBee\ClassContent\Media\Image as MediaImage;
use BackBee\ClassContent\Revision;
use BackBee\Security\Token\BBUserToken;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeeCloud\Job\MediaImageMigrationJob;
use BackBeeCloud\SiteStatusManager;
use BackBeePlanet\Job\JobInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MediaImageMigration implements JobHandlerInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SiteStatusManager
     */
    private $siteStatusMgr;

    /**
     * @var ElasticsearchManager
     */
    private $elasticsearchMgr;

    public function __construct(
        EntityManager $entityManager,
        SiteStatusManager $siteStatusMgr,
        ElasticsearchManager $elasticsearchMgr
    ) {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
        $this->siteStatusMgr = $siteStatusMgr;
        $this->elasticsearchMgr = $elasticsearchMgr;
    }

    public function isMigrationNeeded()
    {
        $count = (int)$this->entityManager->getRepository(MediaImage::class)->createQueryBuilder('i')
            ->select('COUNT(i)')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer)
    {
        $writer->write('  <c2>> Checking if Media/Image migration is needed...</c2>');
        if (!$this->isMigrationNeeded()) {
            $writer->write('  <c2>> No Media/Image found, migration is not needed. Unlocking website...</c2>');
            $this->siteStatusMgr->unlock();
            $writer->write('  <c2>> Unlock done!</c2>');

            return 0;
        }

        $writer->write('');
        $writer->write('  <c2>> Media/Image has been found, starting migration...</c2>');
        $this->migrate();
        $writer->write('  <c2>> Migration done!</c2>');
        $this->siteStatusMgr->updateLockProgressPercent(60);

        $writer->write('');
        $writer->write('  <c2>> Indexing pages into Elasticsearch...</c2>');
        $this->elasticsearchMgr->indexAllPages(true);
        $writer->write('  <c2>> Indexation done!</c2>');
        $this->siteStatusMgr->updateLockProgressPercent(99);

        $writer->write('');
        $writer->write('  <c2>> Unlocking website...</c2>');
        $this->siteStatusMgr->unlock();
        $writer->write('  <c2>> Unlock done!</c2>');

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job)
    {
        return $job instanceof MediaImageMigrationJob;
    }

    private function migrate()
    {
        if (!$this->isMigrationNeeded()) {
            return;
        }

        $this->migrationSetUp();

        $globalContentUids = $this->getGlobalContentUids();
        foreach ($this->getMediaImages() as $mediaImage) {
            $this->entityManager->detach($mediaImage);

            $newUid = md5($mediaImage->getUid());
            if (in_array($mediaImage->getUid(), $globalContentUids)) {
                $newUid = $mediaImage->getUid();
            }

            $this->hardDeleteContentByUid($mediaImage->getUid());
            $basicImage = $this->buildBasicImageWithMediaImage($newUid, $mediaImage);

            $this->entityManager->persist($basicImage);
            $this->entityManager->flush();

            $this->updateRevisionTable($mediaImage, $basicImage);
            $this->updateParentContentTables($mediaImage, $basicImage);
        }

        $this->updateRevisionWithNewState();

        $this->migrationTearDown();
    }

    private function migrationSetUp()
    {
        $this->entityManager->beginTransaction();
        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS=0');
    }

    private function migrationTearDown()
    {
        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS=1');
        $this->entityManager->commit();
    }

    private function getGlobalContentUids()
    {
        return array_column(
            $this->connection->executeQuery(
                'SELECT content_uid FROM global_content'
            )->fetchAll(),
            'content_uid'
        );
    }

    private function getMediaImages()
    {
        $iterator = $this->entityManager->getRepository(MediaImage::class)->createQueryBuilder('i')
            ->getQuery()
            ->iterate();

        foreach ($iterator as $row) {
            yield $row[0];
        }
    }

    private function hardDeleteContentByUid($uid)
    {
        $this->connection->executeUpdate(
            'DELETE FROM content WHERE uid = :uid',
            ['uid' => $uid]
        );
    }

    private function buildBasicImageWithMediaImage($uid, MediaImage $mediaImage)
    {
        $basicImage = new BasicImage($uid);

        $basicImage->setRevision($mediaImage->getRevision());
        $basicImage->setState($mediaImage->getState());

        $basicImage->image->setRevision($mediaImage->getRevision());
        $basicImage->image->setState($mediaImage->getState());

        $basicImage->image->path = $mediaImage->path;
        $basicImage->image->originalname = $mediaImage->originalname;

        $params = $mediaImage->getAllParams();
        foreach ($mediaImage->getDefaultParams() as $attr => $default) {
            if ($params[$attr]['value'] === $default['value']) {
                unset($params[$attr]);
            }
        }

        $basicImageParamKeys = array_keys($basicImage->getAllParams());
        foreach ($params as $key => $data) {
            if (in_array($key, $basicImageParamKeys)) {
                $basicImage->setParam($key, $data['value']);
            }
        }

        return $basicImage;
    }

    private function updateRevisionTable(MediaImage $mediaImage, BasicImage $basicImage)
    {
        $this->connection->executeUpdate(
            'DELETE FROM revision WHERE content_uid = :content_uid AND state = :state',
            [
                'content_uid' => $mediaImage->getUid(),
                'state' => Revision::STATE_COMMITTED,
            ]
        );

        $revisionRepository = $this->entityManager->getRepository(Revision::class);
        $uniqToken = $revisionRepository->getUniqToken();

        $drafts = $revisionRepository->findBy(['_content' => $mediaImage]);
        $this->entityManager->clear();

        foreach ($drafts as $draft) {
            $mediaImage->setDraft($draft);
            $draft->setContent($mediaImage);

            $basicImageDraft = $this->createAndSetDraftForContent($basicImage, $uniqToken);
            $elementImageDraft = $this->createAndSetDraftForContent($basicImage->image, $uniqToken);

            $draftJsonData = $draft->jsonSerialize();
            if (Revision::STATE_TO_DELETE === $draftJsonData['state']) {
                $basicImageDraft->setState(Revision::STATE_TO_DELETE);
                $elementImageDraft->setState(Revision::STATE_TO_DELETE);

                $this->entityManager->persist($basicImageDraft);
                $this->entityManager->persist($elementImageDraft);

                continue;
            }

            if (false != $draftJsonData['parameters']) {
                foreach ($draftJsonData['parameters'] as $attr => $values) {
                    $basicImageDraft->setParam($attr, $values['draft']);
                }

                $this->entityManager->persist($basicImageDraft);
            }

            if (false != $draftJsonData['elements']) {
                foreach ($draftJsonData['elements'] as $attr => $values) {
                    $elementImageDraft->$attr = $values['draft'];
                }

                $this->entityManager->persist($elementImageDraft);
            }
        }

        $this->entityManager->flush();

        $this->connection->executeUpdate(
            'DELETE FROM revision WHERE content_uid = :content_uid',
            ['content_uid' => $mediaImage->getUid()]
        );
    }

    private function updateParentContentTables(MediaImage $mediaImage, BasicImage $basicImage)
    {
        $parentUids = array_column(
            $this->connection->executeQuery(
                'SELECT c.uid as uid
            FROM content_has_subcontent chs
            LEFT JOIN content c ON c.uid = chs.parent_uid
            WHERE chs.content_uid = :content_uid',
                ['content_uid' => $mediaImage->getUid()]
            )->fetchAll(),
            'uid'
        );

        foreach ($parentUids as $parentUid) {
            $parentData = $this->connection->executeQuery(
                'SELECT uid, data FROM content WHERE uid = :uid',
                ['uid' => $parentUid]
            )->fetch();

            $this->executeUpdateDataField('content', $parentData['uid'], $parentData['data'], $mediaImage, $basicImage);

            $revisionData = $this->connection->executeQuery(
                'SELECT uid, data FROM revision WHERE content_uid = :content_uid',
                ['content_uid' => $parentUid]
            )->fetchAll();

            foreach ($revisionData as $revision) {
                $this->executeUpdateDataField(
                    'revision',
                    $revision['uid'],
                    $revision['data'],
                    $mediaImage,
                    $basicImage
                );
            }
        }

        $this->connection->executeUpdate(
            'UPDATE content_has_subcontent SET content_uid = :new_content_uid WHERE content_uid = :content_uid',
            [
                'new_content_uid' => $basicImage->getUid(),
                'content_uid' => $mediaImage->getUid(),
            ]
        );
    }

    private function executeUpdateDataField(
        $tableName,
        $targetUid,
        $originalData,
        MediaImage $mediaImage,
        BasicImage $basicImage
    ) {
        $this->connection->executeUpdate(
            sprintf("UPDATE %s SET data = :data WHERE uid = :target_uid", $tableName),
            [
                'data' => str_replace(
                    ['Media\Image', $mediaImage->getUid()],
                    ['Basic\Image', $basicImage->getUid()],
                    $originalData
                ),
                'target_uid' => $targetUid,
            ]
        );
    }

    private function updateRevisionWithNewState()
    {
        $statement = $this->connection->executeQuery(
            'SELECT uid, data, accept FROM revision WHERE state = ? and classname in (?,?)',
            array_merge(
                [Revision::STATE_ADDED],
                ['Basic\Slider', 'Media\Video']
            )
        );

        foreach ($statement->fetchAll() as $row) {
            $this->updateRevisionAcceptAndDataFields($row['uid'], $row['accept'], $row['data']);
        }
    }

    private function updateRevisionAcceptAndDataFields($uid, $accept, $data)
    {
        $data = str_replace('Media\Image', 'Basic\Image', $data);
        $accept = str_replace('Media\Image', 'Basic\Image', $accept);

        $imageAttrNames = [];
        foreach (unserialize($accept) as $attrName => $classnames) {
            if (['Basic\Image'] === $classnames) {
                $imageAttrNames[] = $attrName;
            }
        }

        $data = unserialize($data);
        foreach ($imageAttrNames as $elementAttrName) {
            if (is_array($data[$elementAttrName])) {
                $data[$elementAttrName] = array_map(
                    function ($element) {
                        $element['Basic\Image'] = md5($element['Basic\Image']);

                        return $element;
                    },
                    $data[$elementAttrName]
                );
            }
        }

        $data = serialize($data);
        $this->connection->executeUpdate(
            'UPDATE revision SET accept = :accept, data = :data WHERE uid = :uid',
            [
                'accept' => $accept,
                'data' => $data,
                'uid' => $uid,
            ]
        );
    }

    private function createAndSetDraftForContent(AbstractClassContent $content, BBUserToken $bbtoken)
    {
        // refreshing content
        $content = $this->entityManager->find(AbstractClassContent::class, $content->getUid());

        $draft = $this->entityManager->getRepository(Revision::class)->checkout($content, $bbtoken);
        $draft->setContent($content);
        $content->setDraft($draft);

        return $draft;
    }
}
