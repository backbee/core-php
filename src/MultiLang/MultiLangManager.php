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

namespace BackBeeCloud\MultiLang;

use BackBee\BBApplication;
use BackBee\Cache\RedisManager;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;
use BackBee\Site\SiteStatusManager;
use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\Entity\PageRedirection;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeePlanet\Job\JobInterface;
use BackBeePlanet\Job\JobManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use InvalidArgumentException;
use LogicException;
use function in_array;

/**
 * Class MultiLangManager
 *
 * @package BackBeeCloud\MultiLang
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class MultiLangManager implements JobHandlerInterface
{
    /**
     * @var BBApplication
     */
    private $app;

    /**
     * @var EntityManager
     */
    private $entityMgr;

    /**
     * @var array
     */
    private $available;

    /**
     * @var SiteStatusManager
     */
    private $siteStatusMgr;

    /**
     * @var RedisManager
     */
    private $redisManager;

    /**
     * MultiLangManager constructor.
     *
     * @param BBApplication $app
     * @param RedisManager  $redisManager
     */
    public function __construct(BBApplication $app, RedisManager $redisManager)
    {
        $this->app = $app;
        $this->entityMgr = $app->getEntityManager();
        $this->siteStatusMgr = $app->getContainer()->get('site_status.manager');
        $this->available = $app->getContainer()->getParameter('languages');
        $this->redisManager = $redisManager;
    }

    /**
     * Is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return null !== $this->getDefaultLang();
    }

    /**
     * Get current lang.
     *
     * @return string|null
     */
    public function getCurrentLang(): ?string
    {
        $request = $this->app->getRequest();
        $page = null;
        $pageUid = $request->query->get('page_uid');

        try {
            if (null !== $pageUid) {
                $page = $this->entityMgr->find(Page::class, $pageUid);
            }

            if (null === $page) {
                $page = $this->entityMgr->getRepository(Page::class)->findOneBy(
                    [
                        '_url' => $this->app->getRequest()->getPathInfo(),
                    ]
                );
            }
        } catch (Exception $exception) {
            $this->app->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        $lang = null;

        if (null !== $page) {
            $lang = $this->getLangByPage($page);
        }

        if (
            null === $lang &&
            1 === preg_match('~^/([a-z]{2})/~', $request->getPathInfo(), $matches) &&
            $this->getLang($matches[1])
        ) {
            $lang = $matches[1];
        }

        return $lang;
    }

    /**
     * Get all languages.
     *
     * @return array
     */
    public function getAllLangs(): array
    {
        $result = [];

        try {
            foreach ($this->available as $id => $label) {
                $data = [
                    'id' => $id,
                    'label' => $label,
                    'is_active' => false,
                    'is_default' => false,
                ];

                if ($lang = $this->entityMgr->find(Lang::class, $id)) {
                    $data['is_active'] = $lang->isActive();
                    $data['is_default'] = $lang->isDefault();
                }

                $result[] = $data;
            }
        } catch (Exception $exception) {
            $this->app->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $result;
    }

    /**
     * Get active language.
     *
     * @return array
     */
    public function getActiveLangs(): array
    {
        return array_filter($this->getAllLangs(), static function (array $lang) {
            return $lang['is_active'];
        });
    }

    /**
     * Check if lang is active.
     *
     * @param $lang
     *
     * @return bool
     */
    public function isLangActive($lang): bool
    {
        $lang = $lang instanceof Lang ? $lang->getLang() : $lang;

        return in_array($lang, array_column($this->getActiveLangs(), 'id'), true);
    }

    /**
     * Get lang by id.
     *
     * @param $id
     *
     * @return array|null
     */
    public function getLang($id): ?array
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

    /**
     * Get default lang.
     *
     * @return array|null
     */
    public function getDefaultLang(): ?array
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

    /**
     * Set default lang.
     *
     * @param $id
     */
    public function setDefaultLang($id): void
    {
        if (null !== $this->getDefaultLang()) {
            throw new LogicException('You have already defined a default lang.');
        }

        if (null === $this->getLang($id)) {
            throw new InvalidArgumentException(sprintf('Lang \'%s\' does not exist', $id));
        }

        if ($this->getSite()) {
            (new JobManager($this->redisManager))->pushJob(new MultiLangJob($this->getSite()->getLabel(), $id));
            $this->siteStatusMgr->lock();
        }
    }

    /**
     * Update lang.
     *
     * @param $id
     * @param $newIsActive
     *
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     */
    public function updateLang($id, $newIsActive): void
    {
        $data = $this->getLang($id);
        if (null === $data) {
            throw new InvalidArgumentException(sprintf('Lang "%s" does not exist', $id));
        }

        $lang = $this->entityMgr->find(Lang::class, $id);
        if (false === $newIsActive) {
            if ($data['is_default']) {
                throw new LogicException(sprintf('You cannot disable the default lang \'%s\'.', $id));
            }

            if (null !== $lang && $this->getSite()) {
                $lang->disable();
                $this->entityMgr->flush($lang);
                $root = $this->getRootByLang($lang);

                if ($root) {
                    $root->setState(Page::STATE_OFFLINE);
                    $this->entityMgr->flush($root);
                }
            }

            $this->redisManager->removePageCache($this->getSite()->getLabel());

            return;
        }

        if ($data['is_active']) {
            return;
        }

        if (null !== $lang && $this->getSite()) {
            $lang->enable();
            $this->entityMgr->flush($lang);
            $root = $this->getRootByLang($lang);

            if ($root) {
                $root->setState(Page::STATE_ONLINE);
                $this->entityMgr->flush($root);
            }

            $this->redisManager->removePageCache($this->getSite()->getLabel());

            return;
        }

        $this->entityMgr->beginTransaction();

        $lang = new Lang($id);
        $lang->enable();
        $this->entityMgr->persist($lang);
        $this->entityMgr->flush($lang);

        $rootUrl = sprintf('/%s/', $lang->getLang());
        $root = $this->entityMgr->getRepository(Page::class)->findOneBy(['_url' => $rootUrl]);
        if (null === $root) {
            $root = $this->app->getContainer()->get('cloud.page_manager')->duplicate(
                $this->entityMgr->getRepository(Page::class)->getRoot($this->getSite()),
                [
                    'title' => 'Home',
                    'url' => $rootUrl,
                    'lang' => $lang->getLang(),
                    'put_content_online' => true,
                ]
            );
            $root->setState(Page::STATE_ONLINE);
            $this->entityMgr->getRepository(Page::class)->saveWithSection($root, $root->getSection());
            $this->entityMgr->flush();

            $pageRedirection = $this->entityMgr->getRepository(PageRedirection::class)->findOneBy(
                [
                    'toRedirect' => '/',
                ]
            );
            if (null !== $pageRedirection) {
                $this->entityMgr->remove($pageRedirection);
            }

            $this->entityMgr->flush();
        }

        $this->app->getContainer()->get('cloud.global_content_factory')->duplicateLogoForLang($id);

        $this->entityMgr->commit();

        if ($this->getSite()) {
            $this->redisManager->removePageCache($this->getSite()->getLabel());
        }
    }

    /**
     * Associate page with this lang.
     *
     * @param Page $page
     * @param Lang $lang
     *
     * @return PageLang
     */
    public function associate(Page $page, Lang $lang): PageLang
    {
        $pageLang = $this->getAssociation($page);
        if (null !== $pageLang) {
            throw new LogicException(
                sprintf(
                    'Page #%s is already associated to language "%s"',
                    $page->getUid(),
                    $pageLang->getLang()->getLang()
                )
            );
        }

        if (!$lang->isActive()) {
            throw new InvalidArgumentException(sprintf('Lang "%s" is not active', $lang->getLang()));
        }

        $pageLang = new PageLang($page, $lang);
        $this->entityMgr->persist($pageLang);

        return $pageLang;
    }

    /**
     * Get association by page.
     *
     * @param Page $page
     *
     * @return null|object|PageLang
     */
    public function getAssociation(Page $page): ?PageLang
    {
        return $this->entityMgr->getRepository(PageLang::class)->findOneBy(
            [
                'page' => $page,
            ]
        );
    }

    /**
     * Get lang by page.
     *
     * @param Page $page
     *
     * @return string|null
     */
    public function getLangByPage(Page $page): ?string
    {
        $association = $this->getAssociation($page);

        return $association ? $association->getLang()->getLang() : null;
    }

    /**
     * Get root page by lang.
     *
     * @param Lang $lang
     *
     * @return null|object
     */
    public function getRootByLang(Lang $lang)
    {
        return $this->entityMgr->getRepository(Page::class)->findOneBy(
            [
                '_url' => sprintf('/%s/', $lang->getLang()),
            ]
        );
    }

    /**
     * Handles the provided job.
     *
     * @param JobInterface $job
     * @param SimpleWriterInterface $writer
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function handle(JobInterface $job, SimpleWriterInterface $writer): void
    {
        if (null !== $this->getDefaultLang()) {
            $writer->write(sprintf('Default lang of site "%s" is already defined.', $job->siteId()));
            $this->siteStatusMgr->unlock();

            return;
        }

        // ensure that lang is activated
        $this->updateLang($job->lang(), true);
        $this->siteStatusMgr->updateLockProgressPercent(10);
        $this->app->getContainer()->get('cloud.global_content_factory')->duplicateMenuForLang($job->lang());

        // define lang as default
        $this->entityMgr->getRepository(Lang::class)->createQueryBuilder('l')
            ->update()
            ->set('l.default', ':default')
            ->setParameter('default', true)
            ->where('l.lang = :id')
            ->setParameter('id', $job->lang())
            ->getQuery()
            ->execute();

        $writer->write(sprintf('Defined "%s" as default lang.', $job->lang()));
        $writer->write('');

        $this->siteStatusMgr->updateLockProgressPercent(15);
        $this->entityMgr->clear();

        // update all pages URLs
        $maxPages = $this->app->getContainer()->get('cloud.page_manager')->count();
        $pagesCount = 0;

        $defaultLang = $this->getDefaultLangEntity();

        if ($defaultLang) {
            $root = $this->entityMgr->getRepository(Page::class)->findOneBy(
                [
                    '_url' => sprintf('/%s/', $defaultLang->getLang()),
                ]
            );

            $pageIterator = $this
                ->entityMgr
                ->getRepository(Page::class)
                ->createQueryBuilder('p')
                ->getQuery()
                ->iterate();

            foreach ($pageIterator as $row) {
                $page = $row[0];
                if ($root && $root !== $page && '/' !== $page->getUrl() && null === $this->getAssociation($page)) {
                    $this->entityMgr->getRepository(Page::class)->insertNodeAsLastChildOf($page, $root);
                    $newUrl = sprintf('/%s%s', $defaultLang->getLang(), $page->getUrl());
                    $pageRedirection = new PageRedirection($page->getUrl(), $newUrl);
                    $this->entityMgr->persist($pageRedirection);
                    $this->associate($page, $defaultLang);
                    $page->setUrl($newUrl);
                    $this->entityMgr->flush();

                    $writer->write(
                        sprintf(
                            '> Migrated page #%s "%s" to default lang.',
                            $page->getUid(),
                            $page->getTitle()
                        )
                    );
                }

                $pagesCount++;
                $this->siteStatusMgr->updateLockProgressPercent(
                    15 + (int)(((($pagesCount / $maxPages) * 100 * 84) / 100))
                );
            }
        }

        $this->siteStatusMgr->updateLockProgressPercent(100);
        $writer->write('');

        $this->siteStatusMgr->unlock();

        $writer->write('');
        $writer->write('This job has been successfully done.');
    }

    /**
     * Returns true if the provided job is supported, else false.
     *
     * @param JobInterface $job
     *
     * @return bool
     */
    public function supports(JobInterface $job): bool
    {
        return $job instanceof MultiLangJob;
    }

    /**
     * Get site.
     *
     * @return null|object|Site
     */
    private function getSite(): ?Site
    {
        return $this->entityMgr->getRepository(Site::class)->findOneBy([]);
    }

    /**
     * Get default lang entity.
     *
     * @return null|object|Lang
     */
    private function getDefaultLangEntity(): ?Lang
    {
        $lang = $this->entityMgr->getRepository(Lang::class)->findOneBy(['default' => true]);

        if (null === $lang) {
            throw new InvalidArgumentException('Cannot find the default lang');
        }

        return $lang;
    }


    /**
     * Is multi language enabled.
     *
     * @return bool
     */
    public function isMultiLanguageEnabled(): bool
    {
        return $this->app->getContainer()->hasParameter('multi_language') ?
            $this->app->getContainer()->getParameter('multi_language') : true;
    }
}
