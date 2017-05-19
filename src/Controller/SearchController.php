<?php

namespace BackBeeCloud\Controller;

use BackBeeCloud\Listener\ClassContent\SearchbarListener;
use BackBeeCloud\PageType\SearchResultType;
use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\MetaData\MetaDataBag;
use BackBee\NestedNode\Page;
use BackBee\Site\Layout;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchController
{
    protected $app;
    protected $request;

    public function __construct(BBApplication $app)
    {
        $this->app = $app;
    }

    public function search()
    {
        return new Response($this->app->getRenderer()->render($this->getSearchResultPage()));
    }

    protected function getSearchResultPage()
    {
        $uid = md5('search_result');
        $entyMgr = $this->app->getEntityManager();
        if (null === $page = $entyMgr->find(Page::class, $uid)) {
            $entyMgr->beginTransaction();
            $page = (new Page($uid))
                ->setTitle('Search result')
                ->setLayout($entyMgr->getRepository(Layout::class)->findOneBy([]))
                ->setState(Page::STATE_ONLINE)
                ->setMetaData(new MetaDataBag())
                ->setUrl('/search')
            ;

            $entyMgr->persist($page);
            $this->app->getContainer()->get('cloud.page_manager')->update($page, [
                'type' => (new SearchResultType())->uniqueName(),
            ]);

            $uids = $this->app->getContainer()->get('cloud.content_manager')->getUidsFromPage(
                $page,
                $this->app->getBBUserToken()
            );
            foreach ($uids as $contentUid) {
                $content = $entyMgr->find(AbstractClassContent::class, $contentUid);
                $content->setState(AbstractClassContent::STATE_NORMAL);
                $entyMgr->flush($content);
            }

            $entyMgr->commit();
        }

        return $page;
    }
}
