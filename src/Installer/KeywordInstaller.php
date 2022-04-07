<?php

/*
 * Copyright (c) 2022 Obione
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

use BackBee\NestedNode\KeyWord;
use Exception;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Class KeywordInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class KeywordInstaller extends AbstractInstaller
{
    /**
     * Create root keyword.
     *
     * @param StyleInterface $io
     */
    public function createRootKeyword(StyleInterface $io): void
    {
        $io->section('Create root keyword');

        $uid = md5('root');

        try {
            if (null !== $this->getEntityManager()->find(KeyWord::class, $uid)) {
                $io->note('Root keyword already exists.');
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

        $keyword = new KeyWord($uid);
        $keyword->setRoot($keyword);
        $keyword->setKeyWord('root');

        try {
            $this->getEntityManager()->persist($keyword);
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

        $io->success('Root keyword has been created.');
    }
}