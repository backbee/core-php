<?php

namespace BackBeeCloud\Controller;

use BackBeeCloud\Listener\ClassContent\SearchbarListener;
use BackBeeCloud\PageType\SearchResultType;
use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Revision;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\MetaData\MetaDataBag;
use BackBee\NestedNode\Page;
use BackBee\Site\Layout;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchController
{
    protected $app;
    protected $request;
    protected $multilangMgr;

    public function __construct(BBApplication $app)
    {
        $this->app = $app;
        $this->multilangMgr = $app->getContainer()->get('multilang_manager');
    }

    public function search($lang = null)
    {
        if (null !== $lang) {
            $data = $this->multilangMgr->getLang($lang);
            if (null === $data || !$data['is_active']) {
                throw new FrontControllerException('', FrontControllerException::NOT_FOUND);
            }
        }

        if (null === $lang && null !== $defaultLang = $this->multilangMgr->getDefaultLang()) {
            return new RedirectResponse($this->app->getRouting()->getUrlByRouteName('cloud.search_i18n', [
                'lang' => $defaultLang['id'],
            ], null, false));
        }

        return new Response($this->app->getRenderer()->render($this->getSearchResultPage($lang)));
    }

    protected function getSearchResultPage($lang = null)
    {
        $uid = md5('search_result' . ($lang ? '_' . $lang : ''));
        $entyMgr = $this->app->getEntityManager();
        if (null === $page = $entyMgr->find(Page::class, $uid)) {
            $entyMgr->beginTransaction();
            $data = [
                'uid'   => $uid,
                'title' => 'Search result',
                'type'  => 'search_result',
            ];
            if ($lang) {
                $data['lang'] = $lang;
            }

            $page = $this->app->getContainer()->get('cloud.page_manager')->create($data, false);
            $page->setState(Page::STATE_ONLINE);
            $page->setUrl($lang ? sprintf('/%s/search', $lang) : '/search');
            $entyMgr->flush();

            $uids = $this->app->getContainer()->get('cloud.content_manager')->getUidsFromPage(
                $page,
                $this->app->getBBUserToken()
            );
            foreach ($uids as $contentUid) {
                $content = $entyMgr->find(AbstractClassContent::class, $contentUid);
                if ($bbtoken = $this->app->getBBUserToken()) {
                    $content->setDraft($entyMgr->getRepository(Revision::class)->getDraft($content, $bbtoken));
                }

                if (null !== $content->getDraft()) {
                    $content->prepareCommitDraft();

                    continue;
                }

                $content->setRevision(1);
                $content->setState(AbstractClassContent::STATE_NORMAL);
            }

            $entyMgr->flush();
            $entyMgr->commit();
        }

        return $page;
    }
}
