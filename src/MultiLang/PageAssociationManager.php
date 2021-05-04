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

use BackBee\NestedNode\Page;
use BackBee\Security\Token\BBUserToken;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageAssociation;
use BackBeeCloud\Entity\PageLang;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Query\Expr\Join;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use function count;

/**
 * Class PageAssociationManager
 *
 * @package BackBeeCloud\MultiLang
 *
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageAssociationManager
{
    /**
     * @var EntityManager
     */
    protected $entityMgr;

    /**
     * @var MultiLangManager
     */
    protected $multiLangMgr;

    /**
     * @var ElasticsearchManager
     */
    protected $elasticsearchMgr;

    /**
     * PageAssociationManager constructor.
     *
     * @param EntityManager        $entryMgr
     * @param MultiLangManager     $multiLangMgr
     * @param ElasticsearchManager $elasticsearchMgr
     */
    public function __construct(
        EntityManager $entryMgr,
        MultiLangManager $multiLangMgr,
        ElasticsearchManager $elasticsearchMgr
    ) {
        $this->entityMgr = $entryMgr;
        $this->multiLangMgr = $multiLangMgr;
        $this->elasticsearchMgr = $elasticsearchMgr;
    }

    /**
     * @param Page $page
     * @param      $term
     *
     * @return ElasticsearchCollection
     */
    public function customSearchPages(Page $page, $term): ElasticsearchCollection
    {
        $this->exceptionIfMultilangNotActivated();

        $esQuery = [
            'query' => [
                'bool' => [
                    'should' => [],
                    'must_not' => [
                        ['match' => ['url' => '/']],
                    ],
                ],
            ],
        ];

        if ($term) {
            $matchPart = ['query' => $term, 'boost' => 2];
            $esQuery['query']['bool']['should'][] = ['match' => ['title' => $matchPart]];
            $esQuery['query']['bool']['should'][] = ['match' => ['title.raw' => $matchPart]];
            $esQuery['query']['bool']['should'][] = ['match' => ['title.folded' => $matchPart]];
            $esQuery['query']['bool']['should'][] = ['match_phrase_prefix' => ['title' => $matchPart]];
            $esQuery['query']['bool']['should'][] = ['match_phrase_prefix' => ['title.folded' => $matchPart]];
        }

        $currentLang = $this->multiLangMgr->getLangByPage($page);

        $langsToExclude = array_keys($this->getAssociatedPages($page));
        $langsToExclude[] = $currentLang;

        foreach ($langsToExclude as $lang) {
            $esQuery['query']['bool']['must_not'][] = ['prefix' => ['url' => sprintf('/%s/', $lang)]];
        }

        $qb = $this->entityMgr->getRepository(PageAssociation::class)->createQueryBuilder('pa');
        $pageLangsToExclude = $qb
            ->select('pl.id as page_lang_id')
            ->join('pa.id', 'pl')
            ->join('pa.page', 'p')
            ->join(PageLang::class, 'ppl', Join::WITH, 'p._uid = ppl.page')
            ->join(Lang::class, 'ppl_l', Join::WITH, 'ppl.lang = ppl_l.lang')
            ->where($qb->expr()->eq('pl.lang', ':lang'))
            ->orWhere($qb->expr()->in('ppl_l.lang', $langsToExclude))
            ->setParameter('lang', $currentLang)
            ->getQuery()
            ->getResult();

        $pagesToExclude = [];
        $pageLangsToExclude = array_values(array_unique(array_column($pageLangsToExclude, 'page_lang_id')));

        if (false !== $pageLangsToExclude && !empty($pageLangsToExclude)) {
            $qb = $this->entityMgr->getRepository(PageAssociation::class)->createQueryBuilder('pa');
            $pagesToExclude = $qb
                ->select('p._uid as page_uid')
                ->join('pa.id', 'pl')
                ->join('pa.page', 'p')
                ->where($qb->expr()->in('pl.id', $pageLangsToExclude))
                ->getQuery()
                ->getResult();
        }

        foreach (array_column($pagesToExclude, 'page_uid') as $pageUid) {
            $esQuery['query']['bool']['must_not'][] = ['match' => ['_id' => $pageUid]];
        }

        return $this->elasticsearchMgr->customSearchPage($esQuery);
    }

    /**
     * Sets associated pages.
     *
     * @param Page $page   - default language page
     * @param Page $target - target page
     *
     * @throws OptimisticLockException
     */
    public function associatePages(Page $page, Page $target): void
    {
        $this->exceptionIfMultilangNotActivated();

        $sourcePageLang = $this->multiLangMgr->getAssociation($page);
        $targetPageLang = $this->multiLangMgr->getAssociation($target);

        if ($sourcePageLang instanceof PageLang && $targetPageLang instanceof PageLang) {
            // Checks if source and target pages have different lang or not
            if ($sourcePageLang->getLang() === $targetPageLang->getLang()) {
                throw new LogicException(
                    sprintf(
                        'Cannot associate page \'%s\' and page \'%s\' because they have same lang (%s).',
                        $page->getUid(),
                        $target->getUid(),
                        $targetPageLang->getLang()->getLang()
                    )
                );
            }

            $defaultAssociation = $this->getAssociationByPage($page);
            $targetAssociation = $this->getAssociationByPage($target);
            if ($defaultAssociation && $targetAssociation) {
                $langs = array_keys($this->getAssociatedPages($page));
                $targetAssociatedPages = $this->getAssociatedPages($target);
                $targetLangs = array_keys($targetAssociatedPages);
                if ((count($langs) + count($targetLangs)) !== count(array_unique(array_merge($langs, $targetLangs)))) {
                    throw new LogicException(
                        sprintf(
                            'Both pages \'%s\' and \'%s\' are not compatible to be associated',
                            $page->getUid(),
                            $target->getUid()
                        )
                    );
                }

                $targetAssociatedPages[] = $targetAssociation->getPage();
                foreach ($targetAssociatedPages as $associatedPage) {
                    $association = $this->getAssociationByPage($associatedPage);
                    if ($association instanceof PageAssociation) {
                        $association->updateId($defaultAssociation->getId());
                    }
                }

                $this->entityMgr->flush();

                return;
            }

            // Checks if source page has already an association for target lang
            $associatedPages = $this->getAssociatedPages($page);
            $targetLang = $targetPageLang->getLang()->getLang();
            if (isset($associatedPages[$targetLang])) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Page \'%s\' has already an association for lang \'%s\'',
                        $page->getUid(),
                        $targetLang
                    )
                );
            }

            if (null === $defaultAssociation && $targetAssociation) {
                $defaultAssociation = new PageAssociation(
                    $targetAssociation->getId(),
                    $page
                );
                $this->entityMgr->persist($defaultAssociation);
            } elseif (null === $targetAssociation && $defaultAssociation) {
                $targetAssociation = new PageAssociation(
                    $defaultAssociation->getId(),
                    $target
                );
                $this->entityMgr->persist($targetAssociation);
            } else {
                $defaultAssociation = new PageAssociation(
                    $sourcePageLang,
                    $page
                );
                $this->entityMgr->persist($defaultAssociation);

                $targetAssociation = new PageAssociation(
                    $sourcePageLang,
                    $target
                );
                $this->entityMgr->persist($targetAssociation);
            }

            $this->entityMgr->flush();
        }
    }

    /**
     * Returns an array indexed by language code with the associated pages of $page.
     *
     * @param Page $page
     *
     * @return array
     */
    public function getAssociatedPages(Page $page): array
    {
        $this->exceptionIfMultilangNotActivated();

        $associatedPages = [];

        $pageAssociation = $this->getAssociationByPage($page);
        if ($pageAssociation) {
            $associations = $this->entityMgr->getRepository(PageAssociation::class)->findBy(
                [
                    'id' => $pageAssociation->getId(),
                ]
            );
            foreach ($associations as $association) {
                $currentPage = $association->getPage();
                if ($currentPage === $page) {
                    continue;
                }

                $currentLang = $this->multiLangMgr->getLangByPage($currentPage);
                if (!$this->multiLangMgr->isLangActive($currentLang)) {
                    continue;
                }

                $associatedPages[$currentLang] = $association->getPage();
            }
        }

        return $associatedPages;
    }

    /**
     * @param Page $page
     * @param      $lang
     *
     * @return Page|null
     */
    public function getAssociatedPage(Page $page, $lang): ?Page
    {
        $this->exceptionIfMultilangNotActivated();

        $associatedPages = $this->getAssociatedPages($page);

        return $associatedPages[$lang] ?? null;
    }

    /**
     * Deletes page association.
     *
     * @param Page $page
     *
     * @throws OptimisticLockException
     */
    public function deleteAssociatedPage(Page $page): void
    {
        $this->exceptionIfMultilangNotActivated();

        $pageAssociation = $this->getAssociationByPage($page);
        if (null === $pageAssociation) {
            return;
        }

        $associatedPages = $this->getAssociatedPages($page);
        if ($pageAssociation->getId()->getPage() === $page) {
            if ($newSourcePage = reset($associatedPages)) {
                $newId = $this->multiLangMgr->getAssociation($newSourcePage);
                foreach ($associatedPages as $associatedPage) {
                    $association = $this->getAssociationByPage($associatedPage);
                    if ($association instanceof PageAssociation && $newId instanceof PageLang) {
                        $association->updateId($newId);
                    }
                }
            }
        } elseif (1 === count($associatedPages)) {
            $rootAssociation = $this->getAssociationByPage(reset($associatedPages));
            $this->entityMgr->remove($rootAssociation);
        }

        $this->entityMgr->remove($pageAssociation);
        $this->entityMgr->flush();
    }

    /**
     * Exception if multi lang not activated.
     */
    protected function exceptionIfMultilangNotActivated(): void
    {
        if (!$this->multiLangMgr->isActive()) {
            throw new RuntimeException('You must enable multilang before using page association manager.');
        }
    }

    /**
     * @param Page $page
     *
     * @return PageAssociation|object|null
     */
    protected function getAssociationByPage(Page $page)
    {
        return $this->entityMgr->getRepository(PageAssociation::class)->findOneBy(
            [
                'page' => $page,
            ]
        );
    }

    /**
     * Get equivalent pages data.
     *
     * @param Page             $currentPage
     * @param BBUserToken|null $bbToken
     *
     * @return array
     */
    public function getEquivalentPagesData(Page $currentPage, BBUserToken $bbToken = null): array
    {
        $equivalentPages = [];
        foreach ($this->getAssociatedPages($currentPage) as $lang => $page) {
            if (null !== $bbToken || $page->isOnline()) {
                $equivalentPages[$lang] = $page;
            }
        }

        return $equivalentPages;
    }
}
