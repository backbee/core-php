<?php

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