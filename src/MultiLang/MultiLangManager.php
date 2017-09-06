<?php

namespace BackBeeCloud\MultiLang;

use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\Entity\PageRedirection;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeePlanet\GlobalSettings;
use BackBeePlanet\Job\JobInterface;
use BackBeePlanet\Job\JobManager;
use BackBeePlanet\RedisManager;
use BackBee\BBApplication;
use BackBee\Bundle\Registry;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class MultiLangManager implements JobHandlerInterface
{
    /**
     * @var BBApplication
     */
    protected $app;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entyMgr;

    /**
     * @var array
     */
    protected $availables;

    public function __construct(BBApplication $app)
    {
        $this->app = $app;
        $this->entyMgr = $app->getEntityManager();
        $this->availables = (new GlobalSettings())->langs();
    }

    public function getCurrentLang()
    {
        $page = null;
        $pageUid = $this->app->getRequest()->query->get('page_uid', null);
        if (null !== $pageUid) {
            $page = $this->entyMgr->find(Page::class, $pageUid);
        }

        if (null === $page) {
            $page = $this->entyMgr->getRepository(Page::class)->findOneBy([
                '_url' => $this->app->getRequest()->getPathInfo(),
            ]);
        }

        return $page ? $this->getLangByPage($page) : null;
    }

    public function getAllLangs()
    {
        $result = [];
        foreach ($this->availables as $id => $label) {
            $data = [
                'id'         => $id,
                'label'      => $label,
                'is_active'  => false,
                'is_default' => false,
            ];
            if ($lang = $this->entyMgr->find(Lang::class, $id)) {
                $data['is_active'] = $lang->isActive();
                $data['is_default'] = $lang->isDefault();
            }

            $result[] = $data;
        }

        return $result;
    }

    public function getLang($id)
    {
        $result = null;
        foreach ($this->getAllLangs() as $lang) {
            if ($id === $lang['id'] || ('default' === $id && $lang['is_default'])) {
                $result = $lang;

                break;
            }
        }

        return $result;
    }

    public function getDefaultLang()
    {
        $result = null;
        foreach ($this->getAllLangs() as $lang) {
            if ($lang['is_default']) {
                $result = $lang;

                break;
            }
        }

        return $result;
    }

    public function setDefaultLang($id)
    {
        if (null !== $this->getDefaultLang()) {
            throw new \LogicException('You have already defined a default lang.');
        }

        if (null === $lang = $this->getLang($id)) {
            throw new \InvalidArgumentException(sprintf('Lang \'%s\' does not exist', $id));
        }

        (new JobManager())->pushJob(new MultiLangJob($this->getSite()->getLabel(), $id));
        $this->lockSite();
    }

    public function updateLang($id, $newIsActive)
    {
        $data = $this->getLang($id);
        if (null === $data) {
            throw new \InvalidArgumentException(sprintf('Lang "%s" does not exist', $id));
        }

        $lang = $this->entyMgr->find(Lang::class, $id);
        if (false === $newIsActive) {
            if ($data['is_default']) {
                throw new \LogicException(sprintf('You cannot disable the default lang \'%s\'.', $id));
            }

            if (null !== $lang) {
                $lang->disable();
                $this->entyMgr->flush($lang);
                $root = $this->getRootByLang($lang);
                $root->setState(Page::STATE_OFFLINE);
                $this->entyMgr->flush($root);
            }

            RedisManager::removePageCache($this->app->getSite()->getLabel());

            return;
        }

        if ($data['is_active']) {
            return;
        }

        if (null !== $lang) {
            $lang->enable();
            $this->entyMgr->flush($lang);
            $root = $this->getRootByLang($lang);
            $root->setState(Page::STATE_ONLINE);
            $this->entyMgr->flush($root);

            RedisManager::removePageCache($this->app->getSite()->getLabel());

            return;
        }

        $this->entyMgr->beginTransaction();

        $lang = new Lang($id);
        $lang->enable();
        $this->entyMgr->persist($lang);
        $this->entyMgr->flush($lang);

        $rootUrl = sprintf('/%s/', $lang->getLang());
        $root = $this->entyMgr->getRepository(Page::class)->findOneBy(['_url' => $rootUrl]);
        if (null === $root) {
            $root = $this->app->getContainer()->get('cloud.page_manager')->duplicate(
                $this->entyMgr->getRepository(Page::class)->getRoot($this->getSite()),
                [
                    'title'              => 'Home',
                    'lang'               => $lang->getLang(),
                    'put_content_online' => true,
                ]
            );
            $root->setState(Page::STATE_ONLINE);
            $this->entyMgr->getRepository(Page::class)->saveWithSection($root, $root->getSection());
            $root->setUrl($rootUrl);
            $this->entyMgr->flush();

            $pageRedirection = $this->entyMgr->getRepository(PageRedirection::class)->findOneBy([
                'toRedirect' => '/',
            ]);
            if (null !== $pageRedirection) {
                $this->entyMgr->remove($pageRedirection);
            }

            $this->entyMgr->flush();
        }

        $this->app->getContainer()->get('cloud.global_content_factory')->duplicateLogoForLang($id);

        $this->entyMgr->commit();

        RedisManager::removePageCache($this->app->getSite()->getLabel());
    }

    public function associate(Page $page, Lang $lang)
    {
        $pagelang = $this->getAssociation($page);
        if (null !== $pagelang) {
            throw new \LogicException(sprintf(
                'Page #%s is already associated to language "%s"',
                $page->getUid(),
                $pagelang->getLang()->getLang()
            ));
        }

        if (!$lang->isActive()) {
            throw new \InvalidArgumentException(sprintf('Lang "%s" is not active', $lang->getLang()));
        }

        $pagelang = new PageLang($page, $lang);
        $this->entyMgr->persist($pagelang);

        return $pagelang;
    }

    public function getAssociation(Page $page)
    {
        return $this->entyMgr->getRepository(PageLang::class)->findOneBy([
            'page' => $page,
        ]);
    }

    /**
     * Handles the provided job.
     *
     * @param JobInterface          $job
     * @param SimpleWriterInterface $writer
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer)
    {
        if (null !== $this->getDefaultLang()) {
            $writer->write(sprintf('Default lang of site "%s" is already defined.', $job->siteId()));
            $this->unlockSite();

            return;
        }

        // ensure that lang is activated
        $this->updateLang($job->lang(), true);
        $this->updateWorkPercent(10);
        $this->app->getContainer()->get('cloud.global_content_factory')->duplicateMenuForLang($job->lang());

        // define lang as default
        $this->entyMgr->getRepository(Lang::class)->createQueryBuilder('l')
            ->update()
            ->set('l.default', ':default')
            ->setParameter('default', true)
            ->where('l.lang = :id')
            ->setParameter('id', $job->lang())
            ->getQuery()
            ->execute()
        ;

        $writer->write(sprintf('Defined "%s" as default lang.', $job->lang()));
        $writer->write('');

        $this->updateWorkPercent(15);
        $this->entyMgr->clear();

        // update all pages URLs
        $maxPages = $this->app->getContainer()->get('cloud.page_manager')->count();
        $pagesCount = 0;

        $defaultLang = $this->entyMgr->getRepository(Lang::class)->findOneBy(['default' => true]);
        $root = $this->entyMgr->getRepository(Page::class)->findOneBy([
            '_url' => sprintf('/%s/', $defaultLang->getLang()),
        ]);
        $pageIterator = $this->entyMgr->getRepository(Page::class)->createQueryBuilder('p')->getQuery()->iterate();
        foreach ($pageIterator as $row) {
            $page = $row[0];
            if ($root !== $page && '/' !== $page->getUrl() && null === $this->getAssociation($page)) {
                $this->entyMgr->getRepository(Page::class)->insertNodeAsLastChildOf($page, $root);
                $newUrl = sprintf('/%s%s', $defaultLang->getLang(), $page->getUrl());
                $pageRedirection = new PageRedirection($page->getUrl(), $newUrl);
                $this->entyMgr->persist($pageRedirection);
                $this->associate($page, $defaultLang);
                $page->setUrl($newUrl);
                $this->entyMgr->flush();

                $writer->write(sprintf(
                    '> Migrated page #%s "%s" to default lang.',
                    $page->getUid(),
                    $page->getTitle()
                ));
            }

            $pagesCount++;
            $this->updateWorkPercent(15 + (int) (((($pagesCount / $maxPages) * 100 * 84) / 100)));
        }

        $this->updateWorkPercent(100);
        $writer->write('');

        $this->unlockSite();

        $writer->write('');
        $writer->write('This job has been successfully done.');
    }

    /**
     * Returns true if the provided job is supported, else false.
     *
     * @param  JobInterface $job
     * @return bool
     */
    public function supports(JobInterface $job)
    {
        return $job instanceof MultiLangJob;
    }

    public function getLangByPage(Page $page)
    {
        $association = $this->getAssociation($page);

        return $association ? $association->getLang()->getLang() : null;
    }

    public function getRootByLang(Lang $lang)
    {
        return $this->entyMgr->getRepository(Page::class)->findOneBy([
            '_url' => sprintf('/%s/', $lang->getLang()),
        ]);
    }

    public function getWorkProgress()
    {
        if (null === $lock = $this->getLock()) {
            throw new \LogicException('Cannot find any work in progress.');
        }

        return (int) $lock->getValue();
    }

    private function getLock()
    {
        return $this->entyMgr->getRepository(Registry::class)->findOneBy([
            'scope' => 'GLOBAL',
            'type'  => 'site_status',
            'key'   => 'work_in_progress',
        ]);
    }

    private function updateWorkPercent($percent)
    {
        if (null === $lock = $this->getLock()) {
            throw new \LogicException('Cannot find any work in progress.');
        }

        $lock->setValue((int) $percent);
        $this->entyMgr->flush($lock);
    }

    private function lockSite()
    {
        if (null === $this->getLock()) {
            $lock = new Registry();
            $lock->setScope('GLOBAL');
            $lock->setType('site_status');
            $lock->setKey('work_in_progress');
            $lock->setValue(0);

            $this->entyMgr->persist($lock);
            $this->entyMgr->flush($lock);
        }
    }

    private function unlockSite()
    {
        if (null !== $lock = $this->getLock()) {
            $this->entyMgr->remove($lock);
            $this->entyMgr->flush($lock);
        }
    }

    private function getSite()
    {
        return $this->entyMgr->getRepository(Site::class)->findOneBy([]);
    }
}
