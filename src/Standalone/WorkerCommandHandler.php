<?php

namespace BackBeePlanet\Standalone;

use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeeCloud\Job\MediaImageMigrationJob;
use BackBeePlanet\Job\JobInterface;
use BackBeePlanet\Job\JobManager;
use BackBeePlanet\Redis\RedisManager;
use BackBee\BBApplication;
use BackBee\Site\Site;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Api\IO\IO;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class WorkerCommandHandler implements SimpleWriterInterface
{
    const JOB_HANDLER_SERVICE_TAG = 'worker.job_handler';

    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var IO
     */
    protected $io;

    public function __construct(BBApplication $app)
    {
        $this->app = $app;
    }

    public function handle(Args $args, IO $io, Command $command)
    {
        $io->writeLine('<info>Run "console worker --help" to get list of available worker command.</info>');

        return 0;
    }

    public function handleJob(Args $args, IO $io, Command $command, JobInterface $job = null)
    {
        $this->io = $io;

        if (null === $job) {
            $io->writeLine('<bu>  --- Start of "worker run-jobs" command ---</bu>');
            $io->writeLine('');

            $jobMgr = new JobManager();
            $job = $jobMgr->pullJob();
            if (null === $job) {
                $io->writeLine('  <comment>No jobs in queue found.</comment>');

                return 0;
            }
        }

        $starttime = microtime(true);

        $dic = $this->app->getContainer();
        $handlers = [];
        foreach ($dic->findTaggedServiceIds(self::JOB_HANDLER_SERVICE_TAG) as $id => $data) {
            if ($dic->has($id) && $dic->get($id) instanceof JobHandlerInterface) {
                $handlers[] = $dic->get($id);
            }
        }

        foreach ($handlers as $handler) {
            if ($handler->supports($job)) {
                $handler->handle($job, $this);
            }
        }

        RedisManager::removePageCache($job->siteId());

        $io->writeLine('');
        $io->writeLine(sprintf(
            '<bu>  --- job successfully done in %ss. ---</bu>',
            number_format(microtime(true) - $starttime, 3)
        ));

        return 0;
    }

    public function handleMediaImageMigration(Args $args, IO $io, Command $command)
    {
        $io->writeLine('<c1>Starting migration of Media/Image to Basic/Image process...</c1>');
        $this->app->getContainer()->get('site_status.manager')->lock();

        $siteLabel = $this->app->getEntityManager()->getRepository(Site::class)->findOneBy([])->getLabel();

        return $this->handleJob($args, $io, $command, new MediaImageMigrationJob($siteLabel));
    }

    /**
     * {@inheritdoc}
     */
    public function write($message, $type = 'info')
    {
        $this->io->writeLine(sprintf('  <%s>%s</%s>', $type, $message, $type));
    }
}
