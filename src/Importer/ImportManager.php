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

namespace BackBeeCloud\Importer;

use BackBee\NestedNode\Page;
use BackBee\Util\StringUtils;
use BackBeeCloud\Entity\ImportStatus;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeeCloud\Structure\StructureBuilder;
use BackBeePlanet\Importer\ImportJob;
use BackBeePlanet\Importer\ReaderInterface;
use BackBeePlanet\Importer\WordpressReader;
use BackBeePlanet\Job\JobInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use InvalidArgumentException;
use function array_key_exists;

/**
 * Class ImportManager
 *
 * @package BackBeeCloud\Importer
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImportManager implements JobHandlerInterface
{
    public const SUPPORTED_TYPE = [
        'wordpress' => WordpressReader::class,
    ];

    /**
     * @var EntityManager
     */
    protected $entityMgr;

    /**
     * @var StructureBuilder
     */
    protected $structBuilder;

    /**
     * ImportManager constructor.
     *
     * @param EntityManager    $entityMgr
     * @param StructureBuilder $structBuilder
     */
    public function __construct(EntityManager $entityMgr, StructureBuilder $structBuilder)
    {
        $this->entityMgr = $entityMgr;
        $this->structBuilder = $structBuilder;
    }

    /**
     * Runs the import process.
     *
     * @param ReaderInterface       $reader
     * @param mixed                 $source
     * @param SimpleWriterInterface $writer
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function import(ReaderInterface $reader, $source, SimpleWriterInterface $writer): void
    {
        if (!$reader->verify($source)) {
            throw new InvalidArgumentException(
                sprintf(
                    '[%s] the provided source is not supported by "%s" importer (%s).',
                    __METHOD__,
                    $reader->name(),
                    get_class($reader)
                )
            );
        }

        $label = sprintf('[%s] %s', strtoupper($reader->name()), $source);
        $importStatus = $this->entityMgr->getRepository(ImportStatus::class)->findOneBy(
            [
                'label' => $label,
            ]
        );
        if (null === $importStatus) {
            $importStatus = new ImportStatus($label, $reader->sourceMetadata($source)['max_items']);
            $this->entityMgr->persist($importStatus);
        }

        $writer->write(sprintf('Starts to import articles from "%s %s"...', $reader->name(), $source));
        $writer->write('');

        $count = $importStatus->importedCount();
        foreach ($reader->collect($source) as $row) {
            if (false === $row) {
                break;
            }

            $starttime = microtime(true);
            if (null !== $this->entityMgr->find(Page::class, $row['page']['uid'])) {
                $writer->write(
                    sprintf(
                        '- already imported article "%s", skipped.',
                        html_entity_decode($row['page']['title'])
                    )
                );

                $this->entityMgr->clear();
                gc_collect_cycles();

                continue;
            }

            $this->structBuilder->buildPage($row['page'], $row['contents'], true);

            $writer->write(
                sprintf(
                    '- [%d/%d %d%% %s - %ss] imported article "%s"',
                    ++$count,
                    $importStatus->maxCount(),
                    $importStatus->statusPercent(),
                    StringUtils::formatBytes(memory_get_usage()),
                    number_format(microtime(true) - $starttime, 3),
                    $row['page']['title']
                )
            );

            $importStatus = $this->entityMgr->getRepository(ImportStatus::class)->findOneBy(
                [
                    'label' => $label,
                ]
            );
            $importStatus->incrImportedCount();
            $this->entityMgr->flush($importStatus);

            $this->entityMgr->clear();
            gc_collect_cycles();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer): int
    {
        $writer->write('Starts of import job.');
        $writer->write('');

        $reader = $this->getReaderOf($job->type());
        if (null === $reader) {
            $writer->write(
                sprintf(
                    '<error>Cannot find reader for type "%s". Import aborted for site "%s".</error>',
                    $job->type(),
                    $job->siteId()
                )
            );

            return 1;
        }

        if (!$reader->verify($job->source())) {
            $writer->write(
                sprintf(
                    '<error>Cannot find reader for type "%s". Import aborted for site "%s".</error>',
                    $job->type(),
                    $job->siteId()
                )
            );

            return 1;
        }

        $this->import($reader, $job->source(), $writer);

        $writer->write('');
        $writer->write(sprintf('All pages are now imported from %s (%s).', $job->type(), $job->source()));
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job): bool
    {
        return $job instanceof ImportJob;
    }

    /**
     * Returns an instance of reader depending of the requested type. It can return
     * null if the type does not match any reader.
     *
     * @param string $type
     *
     * @return ReaderInterface|null
     */
    public function getReaderOf(string $type): ?ReaderInterface
    {
        $reader = null;
        if ($this->isSupportedType($type)) {
            $classname = self::SUPPORTED_TYPE[$type];
            $reader = new $classname();
        }

        return $reader;
    }

    /**
     * Returns true if the given type is supported, else false.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isSupportedType(string $type): bool
    {
        return array_key_exists($type, self::SUPPORTED_TYPE);
    }
}
