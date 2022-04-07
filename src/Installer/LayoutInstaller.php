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

use BackBee\Site\Layout;
use Exception;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Class LayoutInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class LayoutInstaller extends AbstractInstaller
{
    /**
     * Create clean layout.
     *
     * @param StyleInterface $io
     */
    public function createCleanLayout(StyleInterface $io): void
    {
        $io->section('Create clean layout');

        if (null !== $this->getEntityManager()->getRepository(Layout::class)->findOneBy([])) {
            $io->note('Clean layout already exists.');
            return;
        }

        $layout = new Layout(md5('clean-layout'));
        $layout
            ->setData('{"templateLayouts":[{"title":"Main zone","layoutSize":{"height":300,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1332943638139_1","layoutClass":"bb4ResizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":5,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","position":"none","height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":true,"accept":[],"maxentry":"0","defaultClassContent":null}]}')
            ->setLabel('Clean layout')
            ->setPath('CleanLayout.twig')
            ->setPicPath($layout->getUid() . '.png')
        ;

        try {
            $this->getEntityManager()->persist($layout);
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

        $io->success('Clean layout has been created.');
    }
}