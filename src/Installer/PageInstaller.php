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

namespace BackBee\Installer;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\NestedNode\Page;
use BackBee\Site\Layout;
use BackBee\Site\Site;
use Exception;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Class PageInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageInstaller extends AbstractInstaller
{
    /**
     * Create root page.
     *
     * @param StyleInterface $io
     */
    public function createRootPage(StyleInterface $io): void
    {
        $io->section('Create root page');

        $uid = md5('root-page');

        try {
            if (null !== $this->getEntityManager()->find(Page::class, $uid)) {
                $io->note('Home page already exists.');
                return;
            }
        } catch (Exception $exception) {
            $io->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $page = new Page($uid);

        try {
            $page
                ->setTitle('Home')
                ->setLayout($this->getEntityManager()->getRepository(Layout::class)->findOneBy([]))
                ->setSite($this->getEntityManager()->getRepository(Site::class)->findOneBy([]))
                ->setUrl('/')
                ->setState(Page::STATE_ONLINE)
                ->setPosition(1)
            ;
        } catch (Exception $exception) {
            $io->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        try {
            $page
                ->getContentSet()
                ->setRevision(1)
                ->setState(AbstractClassContent::STATE_NORMAL)
                ->first()
                ->setRevision(1)
                ->setState(AbstractClassContent::STATE_NORMAL)
            ;

            $this->getEntityManager()->persist($page);
            $this->getEntityManager()->flush();
        } catch (Exception $exception) {
            $io->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $io->success('Home page has been created.');
    }
}