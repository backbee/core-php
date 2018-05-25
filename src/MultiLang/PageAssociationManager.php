<?php

namespace BackBeeCloud\MultiLang;

use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\Entity\Lang;
use BackBeeCloud\Entity\PageAssociation;
use BackBeeCloud\Entity\PageLang;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBee\NestedNode\Page;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @author Alina Pascalau <alina.pascalau@lp-digital.fr>
 */
class PageAssociationManager
{
    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var MultiLangManager
     */
    protected $multilangMgr;

    /**
     * @var ElasticsearchManager
     */
    protected $elasticsearchMgr;

    public function __construct(
        EntityManager $entyMgr,
        MultiLangManager $multilangMgr,
        ElasticsearchManager $elasticsearchMgr
    ) {
        $this->entyMgr      = $entyMgr;
        $this->multilangMgr = $multilangMgr;
        $this->elasticsearchMgr = $elasticsearchMgr;
    }

    public function customSearchPages(Page $page, $term)
    {
        $this->exceptionIfMultilangNotActivated();

        $esQuery = [
            'query' => [
                'bool' => [
                    'should' => [],
                    'must_not' => [
                        [ 'match' => ['url' => '/'] ]
                    ],
                ],
            ],
        ];

        if ($term) {
            $matchPart = ['query' => $term, 'boost' => 2];
            $esQuery['query']['bool']['should'][] = [ 'match' => ['title' => $matchPart] ];
            $esQuery['query']['bool']['should'][] = [ 'match' => ['title.raw' => $matchPart] ];
            $esQuery['query']['bool']['should'][] = [ 'match' => ['title.folded' => $matchPart] ];
            $esQuery['query']['bool']['should'][] = [ 'match_phrase_prefix' => ['title' => $matchPart] ];
            $esQuery['query']['bool']['should'][] = [ 'match_phrase_prefix' => ['title.raw' => $matchPart] ];
            $esQuery['query']['bool']['should'][] = [ 'match_phrase_prefix' => ['title.folded' => $matchPart] ];
        }

        $currentLang = $this->multilangMgr->getLangByPage($page);

        $langsToExclude = array_keys($this->getAssociatedPages($page));
        $langsToExclude[] = $currentLang;

        foreach ($langsToExclude as $lang) {
            $esQuery['query']['bool']['must_not'][] = [ 'prefix' => ['url' => sprintf('/%s/', $lang)] ];
        }

        $qb = $this->entyMgr->getRepository(PageAssociation::class)->createQueryBuilder('pa');
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
        if (false != $pageLangsToExclude) {
            $qb = $this->entyMgr->getRepository(PageAssociation::class)->createQueryBuilder('pa');
            $pagesToExclude = $qb
                ->select('p._uid as page_uid')
                ->join('pa.id', 'pl')
                ->join('pa.page', 'p')
                ->where($qb->expr()->in('pl.id', $pageLangsToExclude))
                ->getQuery()
                ->getResult();
        }

        foreach (array_column($pagesToExclude, 'page_uid') as $pageUid) {
            $esQuery['query']['bool']['must_not'][] = [ 'match' => ['_id' => $pageUid] ];
        }

        return $this->elasticsearchMgr->customSearchPage($esQuery);
    }

    /**
     * Sets associated pages.
     *
     * @param Page $page - default language page
     * @param Page $target - target page
     */
    public function associatePages(Page $page, Page $target)
    {
        $this->exceptionIfMultilangNotActivated();

        $sourcePageLang = $this->multilangMgr->getAssociation($page);
        $targetPageLang = $this->multilangMgr->getAssociation($target);

        // Checks if source and target pages have different lang or not
        if ($sourcePageLang->getLang() === $targetPageLang->getLang()) {
            throw new \LogicException(sprintf(
                'Cannot associate page \'%s\' and page \'%s\' because they have same lang (%s).',
                $page->getUid(),
                $target->getUid(),
                $targetPageLang->getLang()->getLang()
            ));
        }

        $defaultAssociation = $this->getAssociationByPage($page);
        $targetAssociation = $this->getAssociationByPage($target);
        if ($defaultAssociation && $targetAssociation) {
            $langs = array_keys($this->getAssociatedPages($page));
            $targetAssociatedPages = $this->getAssociatedPages($target);
            $targetLangs = array_keys($targetAssociatedPages);
            if ((count($langs) + count($targetLangs)) !== count(array_unique(array_merge($langs, $targetLangs)))) {
                throw new \LogicException(sprintf(
                    'Both pages \'%s\' and \'%s\' are not compatible to be associated',
                    $page->getUid(),
                    $target->getUid()
                ));
            }

            $targetAssociatedPages[] = $targetAssociation->getPage();
            foreach ($targetAssociatedPages as $associatedPage) {
                $association = $this->getAssociationByPage($associatedPage);
                $association->updateId($defaultAssociation->getId());
            }

            $this->entyMgr->flush();

            return;
        }

        // Checks if source page has already an association for target lang
        $associatedPages = $this->getAssociatedPages($page);
        $targetLang = $targetPageLang->getLang()->getLang();
        if (isset($associatedPages[$targetLang])) {
            throw new \InvalidArgumentException(sprintf(
                'Page \'%s\' has already an association for lang \'%s\'',
                $page->getUid(),
                $targetLang
            ));
        }

        if (null === $defaultAssociation && $targetAssociation) {
            $defaultAssociation = new PageAssociation(
                $targetAssociation->getId(),
                $page
            );
            $this->entyMgr->persist($defaultAssociation);
        } elseif (null === $targetAssociation && $defaultAssociation) {
            $targetAssociation = new PageAssociation(
                $defaultAssociation->getId(),
                $target
            );
            $this->entyMgr->persist($targetAssociation);
        } else {
            $defaultAssociation = new PageAssociation(
                $sourcePageLang,
                $page
            );
            $this->entyMgr->persist($defaultAssociation);

            $targetAssociation = new PageAssociation(
                $sourcePageLang,
                $target
            );
            $this->entyMgr->persist($targetAssociation);
        }

        $this->entyMgr->flush();
    }

    /**
     * Returns an array indexed by language code with the associated pages of $page.
     *
     * @param Page $page
     *
     * @return array
     */
    public function getAssociatedPages(Page $page)
    {
        $this->exceptionIfMultilangNotActivated();

        $associatedPages = [];

        $pageAssociation = $this->getAssociationByPage($page);
        if ($pageAssociation) {
            $associations = $this->entyMgr->getRepository(PageAssociation::class)->findBy([
                'id' => $pageAssociation->getId(),
            ]);
            foreach ($associations as $association) {
                $currentPage = $association->getPage();
                if ($currentPage === $page) {
                    continue;
                }

                $currentLang = $this->multilangMgr->getLangByPage($currentPage);
                if (!$this->multilangMgr->isLangActive($currentLang)) {
                    continue;
                }

                $associatedPages[$currentLang] = $association->getPage();
            }
        }

        return $associatedPages;
    }

    /**
     * @param Page $page
     * @param string $lg
     *
     * @return Page|null
     */
    public function getAssociatedPage(Page $page, $lang)
    {
        $this->exceptionIfMultilangNotActivated();

        $associatedPages = $this->getAssociatedPages($page);

        return isset($associatedPages[$lang]) ? $associatedPages[$lang] : null;
    }

    /**
     * Deletes page association.
     *
     * @param Page $page
     */
    public function deleteAssociatedPage(Page $page)
    {
        $this->exceptionIfMultilangNotActivated();

        $pageAssociation = $this->getAssociationByPage($page);
        if (null === $pageAssociation) {
            return;
        }

        $associatedPages = $this->getAssociatedPages($page);
        if ($pageAssociation->getId()->getPage() === $page) {
            if ($newSourcePage = reset($associatedPages)) {
                $newId = $this->multilangMgr->getAssociation($newSourcePage);
                foreach ($associatedPages as $associatedPage) {
                    $association = $this->getAssociationByPage($associatedPage);
                    $association->updateId($newId);
                }
            }
        } elseif (1 === count($associatedPages)) {
            $rootAssociation = $this->getAssociationByPage(reset($associatedPages));
            $this->entyMgr->remove($rootAssociation);
        }

        $this->entyMgr->remove($pageAssociation);
        $this->entyMgr->flush();
    }

    protected function exceptionIfMultilangNotActivated()
    {
        if (!$this->multilangMgr->isActive()) {
            throw new \RuntimeException('You must enable multilang before using page association manager.');
        }
    }

    protected function getAssociationByPage(Page $page)
    {
        return $this->entyMgr->getRepository(PageAssociation::class)->findOneBy([
            'page' => $page,
        ]);
    }
}
