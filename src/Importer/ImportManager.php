<?php

namespace BackBeeCloud\Importer;

use BackBeeCloud\Entity\ImportStatus;
use BackBeeCloud\ExecutionHelper;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeeCloud\Structure\StructureBuilder;
use BackBeePlanet\Importer\ImportJob;
use BackBeePlanet\Importer\ImportManager as BaseImportManager;
use BackBeePlanet\Importer\ReaderInterface;
use BackBeePlanet\Job\JobInterface;
use BackBee\NestedNode\Page;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImportManager extends BaseImportManager implements JobHandlerInterface
{
    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var StructureBuilder
     */
    protected $structBuilder;

    public function __construct(EntityManager $entyMgr, StructureBuilder $structBuilder)
    {
        $this->entyMgr = $entyMgr;
        $this->structBuilder = $structBuilder;
    }

    /**
     * Runs the import process.
     *
     * @param  ReaderInterface $reader
     * @param  mixed           $source
     */
    public function import(ReaderInterface $reader, $source, SimpleWriterInterface $writer)
    {
        if (!$reader->verify($source)) {
            throw new \InvalidArgumentException(sprintf(
                '[%s] the provided source is not supported by "%s" importer (%s).',
                __METHOD__,
                $reader->name(),
                get_class($reader)
            ));
        }

        $label = sprintf('[%s] %s', strtoupper($reader->name()), $source);
        $importStatus = $this->entyMgr->getRepository(ImportStatus::class)->findOneBy([
            'label' => $label,
        ]);
        if (null === $importStatus) {
            $importStatus = new ImportStatus($label, $reader->sourceMetadata($source)['max_items']);
            $this->entyMgr->persist($importStatus);
        }

        $writer->write(sprintf('Starts to import articles from "%s %s"...', $reader->name(), $source));
        $writer->write('');

        $count = $importStatus->importedCount();
        foreach ($reader->collect($source) as $row) {
            if (false == $row) {
                break;
            }

            $starttime = microtime(true);
            if (null !== $this->entyMgr->find(Page::class, $row['page']['uid'])) {
                $writer->write(sprintf(
                    '- already imported article "%s", skipped.',
                    html_entity_decode($row['page']['title'])
                ), 'c2');

                $this->entyMgr->clear();
                gc_collect_cycles();

                continue;
            }

            $this->structBuilder->buildPage($row['page'], $row['contents'], true);

            $writer->write(sprintf(
                '- [%d/%d %d%% %s - %ss] imported article "%s"',
                ++$count,
                $importStatus->maxCount(),
                $importStatus->statusPercent(),
                ExecutionHelper::formatByte(memory_get_usage()),
                number_format(microtime(true) - $starttime, 3),
                $row['page']['title']
            ));

            $importStatus = $this->entyMgr->getRepository(ImportStatus::class)->findOneBy([
                'label' => $label,
            ]);
            $importStatus->incrImportedCount();
            $this->entyMgr->flush($importStatus);

            $this->entyMgr->clear();
            gc_collect_cycles();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer)
    {
        $writer->write('Starts of import job.');
        $writer->write('');

        $reader = $this->getReaderOf($job->type());
        if (null === $reader) {
            $writer->write(sprintf(
                '<error>Cannot find reader for type "%s". Import aborted for site "%s".</error>',
                $job->type(),
                $job->siteId()
            ));

            return 1;
        }

        if (!$reader->verify($job->source())) {
            $writer->write(sprintf(
                '<error>Cannot find reader for type "%s". Import aborted for site "%s".</error>',
                $job->type(),
                $job->siteId()
            ));

            return 1;
        }

        $this->import($reader, $job->source(), $writer);

        $writer->write('');
        $writer->write(sprintf('All pages are now imported from %s (%s).', $job->type(), $job->source()));
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job)
    {
        return $job instanceof ImportJob;
    }
}
