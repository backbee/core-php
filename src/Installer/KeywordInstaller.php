<?php

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