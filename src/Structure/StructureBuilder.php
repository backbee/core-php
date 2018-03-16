<?php

namespace BackBeeCloud\Structure;

use BackBee\BBApplication;
use BackBee\ClassContent\Basic\Menu;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class StructureBuilder
{
    use ClassContentHelperTrait;

    const MENU_NONE = 'none';
    const MENU_HEADER = 'header';
    const MENU_FOOTER = 'footer';
    const MENU_BOTH = 'both';

    protected $entyMgr;
    protected $pageMgr;
    protected $themeCore;
    protected $schemaParser;
    protected $contentBuilder;
    protected $elasticsearchMgr;
    protected $globalContentFactory;

    public function __construct(BBApplication $app, SchemaParserInterface $schemaParser)
    {
        $this->schemaParser = $schemaParser;
        $this->entyMgr = $app->getEntityManager();
        $this->themeCore = $app->getBundle('theme');
        $this->pageMgr = $app->getContainer()->get('cloud.page_manager');
        $this->elasticsearchMgr = $app->getContainer()->get('elasticsearch.manager');
        $this->contentBuilder = $app->getContainer()->get('cloud.structure.content_builder');
        $this->globalContentFactory = $app->getContainer()->get('cloud.global_content_factory');
    }

    /**
     * Gets schema with the provided name and build site structure.
     *
     * @param  string $name
     */
    public function build($name)
    {
        $schema = $this->getSchema($name)['schema'];

        if (isset($schema['theme'])) {
            $this->themeCore->selectTheme($schema['theme']);
        }

        if (isset($schema['logo_header'])) {
            $this->contentBuilder->hydrateContent(
                $this->globalContentFactory->getHeaderLogo(),
                ['path' => $schema['logo_header']]
            );
        }

        if (isset($schema['logo_footer'])) {
            $this->contentBuilder->hydrateContent(
                $this->globalContentFactory->getFooterLogo(),
                ['path' => $schema['logo_footer']]
            );
        }

        if (isset($schema['header']) && is_array($schema['header'])) {
            foreach ($schema['header'] as $attr => $data) {
                $content = $this->globalContentFactory->getHeaderContent($attr, $data['type']);
                $this->contentBuilder->hydrateContent($content, $data['data']);
            }
        }

        if (isset($schema['footer']) && is_array($schema['footer'])) {
            foreach ($schema['footer'] as $attr => $data) {
                $content = $this->globalContentFactory->getFooterContent($attr, $data['type']);
                $this->contentBuilder->hydrateContent($content, $data['data']);
            }
        }

        if (isset($schema['pages']) && is_array($schema['pages'])) {
            foreach ($schema['pages'] as $data) {
                $this->buildPage([
                    'type'  => $data['type'],
                    'title' => $data['title'],
                    'tags'  => array_map(function ($tag) {
                        return [
                            'label' => $tag,
                        ];
                    }, $data['tags']),
                    'menu'  => $data['menu'],
                ], $data['contents'], true);
            }
        }

        $this->entyMgr->flush();
    }

    /**
     * Builds page with provided data.
     *
     * @param  array $pageData
     * @param  array $contents
     * @param  bool  $doFlush
     * @return Page
     * @throws \InvalidArgumentException if "type", "title" and "menu" are not provided in page data
     */
    public function buildPage(array $pageData, array $contents = [], $doFlush = false)
    {
        if (
            !isset($pageData['type'])
            || !isset($pageData['title'])
            || !isset($pageData['menu'])
        ) {
            throw new \InvalidArgumentException(sprintf(
                '[%s] page data must contain at least "type", title" and "menu" options.',
                __METHOD__
            ));
        }

        $this->pageMgr->disablePageHydratation();

        $page = null;
        $menu = $pageData['menu'];
        unset($pageData['menu']);
        $randomDatetime = new \DateTime(date('Y-m-d H:i:s', time() - rand(1, 1000)));
        if ('home' !== $pageData['type']) {
            if (!isset($pageData['uid'])) {
                $pageData['uid'] = md5($pageData['title']);
            }

            $page = $this->pageMgr->create($pageData);
            $page->setState(Page::STATE_ONLINE);
            if (null === $page->getPublishing()) {
                $page->setPublishing($randomDatetime);
            }
        } else {
            $site = $this->entyMgr->getRepository(Site::class)->findOneBy([]);
            $page = $this->entyMgr->getRepository(Page::class)->getRoot($site);
            $this->pageMgr->update($page, [
                'type' => $pageData['type'],
            ], false);
        }

        if (!isset($pageData['modified_at'])) {
            $page->setModified($randomDatetime);
        }

        $this->handlePageWithMenu($page, $menu);
        $this->contentBuilder->hydrateContents($page, $contents);

        if ($doFlush) {
            $this->entyMgr->flush();
            $this->elasticsearchMgr->indexPage($page);
        }

        $this->pageMgr->enablePageHydratation();

        return $page;
    }

    /**
     * Returns structure schema associated to the provided name.
     *
     * @param  string $name
     * @return array
     * @throws \InvalidArgumentException if the provided structure name does not exist or if the schame file is not
     *                                   readable.
     */
    public function getSchema($name)
    {
        $data = $this->schemaParser->getSchema($name);
        $data['schema'] = $data['schema'] ?: [];

        return $data;
    }

    /**
     * Injects the page into menus if required.
     *
     * @param  Page   $page
     * @param  string $toMenu
     */
    protected function handlePageWithMenu(Page $page, $toMenu)
    {
        if (
            self::MENU_NONE === $toMenu
            || !in_array($toMenu, [self::MENU_HEADER, self::MENU_FOOTER, self::MENU_BOTH])
        ) {
            return $this;
        }

        $fnAddItem = function (Menu $menu, Page $page) {
            $items = $menu->getParamValue('items');
            foreach ($items as $item) {
                if ($item['id'] === $page->getUid()) {
                    return;
                }
            }

            $items[] = [
                'id'    => $page->getUid(),
                'url'   => $page->getUrl(),
                'label' => $page->getTitle(),
            ];
            $menu->setParam('items', $items);
        };

        if (self::MENU_HEADER === $toMenu || self::MENU_BOTH === $toMenu) {
            $fnAddItem($this->globalContentFactory->getHeaderMenu(), $page);
        }

        if (self::MENU_FOOTER === $toMenu || self::MENU_BOTH === $toMenu) {
            $fnAddItem($this->globalContentFactory->getFooterMenu(), $page);
        }
    }
}
