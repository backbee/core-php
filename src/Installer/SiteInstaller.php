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

use BackBee\Command\AbstractCommand;
use BackBee\Site\Site;
use Exception;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Class SiteInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SiteInstaller extends AbstractInstaller
{
    /**
     * Creates a site if it does not exist.
     *
     * @param string         $appName
     * @param StyleInterface $io
     */
    public function createSite(string $appName, StyleInterface $io): void
    {
        $io->section('Create site');

        if (null !== $this->getEntityManager()->getRepository(Site::class)->findOneBy([])) {
            $io->note('Site already exists.');
            return;
        }

        $io->text('Site creation');
        $io->newLine();

        $domain = $this->getSiteDomain();

        $site = new Site(md5($appName));
        $site->setLabel($appName);
        $site->setServerName($domain);

        try {
            $this->getEntityManager()->persist($site);
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

        $io->success(sprintf('Site %s (%s) has been created.', $appName, $domain));
    }

    /**
     * Get site domain.
     *
     * @return string
     */
    private function getSiteDomain(): string
    {
        if (null === ($siteDomain = AbstractCommand::getInput()->getOption('server_name'))) {
            $siteDomain = AbstractCommand::askFor('Site domain: ');
        }

        return $siteDomain;
    }
}