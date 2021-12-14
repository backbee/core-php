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

namespace BackBee\Command;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\NestedNode\Page;
use DateTime;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class AutoPublishPagesCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class AutoPublishPagesCommand extends AbstractCommand
{
    public const LIMIT = 10000;

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
            ->setName('page:publishing')
            ->setDescription('Auto publishing pages.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->section('Preparing to auto-publish pages.');

        $this->cleanMemoryUsage();
        $this->autoPublishPages();

        return 0;
    }

    /**
     * Auto publish pages.
     *
     * @return bool
     */
    protected function autoPublishPages(): bool
    {
        $startTime = microtime(true);
        $now = new DateTime();
        $pageNumber = 0;

        $pages = $this->getContainer()->get('cloud.search_manager')->getBy(
            ['is_online' => false],
            0,
            self::LIMIT
        );

        foreach ($pages as $page) {
            $pagePublishing = $page->getPublishing();
            if (($pagePublishing !== null) && ($now >= $pagePublishing)) {
                $this->getEntityManager()->beginTransaction();

                $page->setState(Page::STATE_ONLINE);
                $page->setPublishing(new DateTime());
                $page->setModified(new DateTime());

                try {
                    foreach ($this->getContainer()->get('cloud.content_manager')->getUidsFromPage(
                        $page
                    ) as $contentUid) {
                        $content = $this->getEntityManager()->find(AbstractClassContent::class, $contentUid);
                        if ($content) {
                            $content->setRevision(1);
                            $content->setState(AbstractClassContent::STATE_NORMAL);
                        }
                    }

                    // flush db
                    $this->getEntityManager()->flush();
                    $this->getEntityManager()->commit();
                } catch (Exception $exception) {
                    $this->getLogger()->error(
                        sprintf(
                            '%s : %s :%s',
                            __CLASS__,
                            __FUNCTION__,
                            $exception->getMessage()
                        )
                    );
                }

                // index es
                $this->getContainer()->get('elasticsearch.manager')->indexPage($page);

                $pageNumber++;

                $this->io->text(
                    sprintf(
                        'Pages processed "%s" (memory usage: %s - duration: %ss)',
                        $page->getTitle(),
                        $this->getPrettyMemoryUsage(),
                        number_format(microtime(true) - $startTime, 3)
                    )
                );
            }
        }

        $this->io->success($pageNumber > 0 ? 'Pages have been successfully published.' : 'No page to publish.');

        return true;
    }
}
