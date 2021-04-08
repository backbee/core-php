<?php

namespace BackBee\Command;

use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeePlanet\Job\JobManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class RunJobCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class RunJobCommand extends AbstractCommand implements SimpleWriterInterface
{
    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('worker:rj')
            ->setDescription('Tries to get job from redis and executes it.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->io = new SymfonyStyle($input, $output);
        $job = $this->getJob();

        if (null === $this->getJob()) {
            $this->io->section('Start of "worker run-jobs" command');
            $jobMgr = new JobManager($this->getContainer()->get('core.redis.manager'));
            $job = $jobMgr->pullJob();
            if (null === $job) {
                $this->io->success('No jobs in queue found.');
                return 0;
            }
        }

        $startTime = microtime(true);
        $dic = $this->getBBApp()->getContainer();
        $handlers = [];

        foreach ($dic->findTaggedServiceIds('worker.job_handler') as $id => $data) {
            if ($dic->has($id) && $dic->get($id) instanceof JobHandlerInterface) {
                $handlers[] = $dic->get($id);
            }
        }

        foreach ($handlers as $handler) {
            if ($handler->supports($job)) {
                $handler->handle($job, $this);
            }
        }

        $this->getContainer()->get('core.redis.manager')->removePageCache($job->siteId());

        $this->io->success(
            sprintf(
                'Job successfully done in %ss.',
                number_format(microtime(true) - $startTime, 3)
            )
        );

        return 0;
    }

    /**
     * Write.
     *
     * @param $message
     */
    public function write($message): void
    {
        $this->io->note($message);
    }
}
