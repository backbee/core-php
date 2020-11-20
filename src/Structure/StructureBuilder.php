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
    protected $designButtonManager;
    protected $designGlobalContentManager;
    protected $designColorPanelManager;
    protected $designThemeColorManager;

    public function __construct(BBApplication $app, SchemaParserInterface $schemaParser)
    {
        $this->schemaParser = $schemaParser;
        $this->entyMgr = $app->getEntityManager();
        $this->themeCore = $app->getBundle('theme');
        $this->pageMgr = $app->getContainer()->get('cloud.page_manager');
        $this->elasticsearchMgr = $app->getContainer()->get('elasticsearch.manager');
        $this->contentBuilder = $app->getContainer()->get('cloud.structure.content_builder');
        $this->globalContentFactory = $app->getContainer()->get('cloud.global_content_factory');
        $this->designButtonManager = $app->getContainer()->get('cloud.design.button.manager');
        $this->designGlobalContentManager = $app->getContainer()->get('cloud.design.global.content.manager');
        $this->designColorPanelManager = $app->getContainer()->get('cloud.color_panel.manager');
        $this->designThemeColorManager = $app->getContainer()->get('cloud.theme_color.manager');
    }

    /**
     * Gets schema with the provided name and build site structure.
     *
     * @param  string $name
     */
    public function build($name)
    {
        $schema = $this->getSchema($name)['schema'];

        if (isset($schema['design_settings'])) {
            $designSettings = $schema['design_settings'];

            // update theme color
            $this->designColorPanelManager->changeThemeColor($designSettings['theme_color']);

            // update color panel
            $this->designColorPanelManager->updateColorPanel($designSettings['color_panel']);

            // update buttons settings
            $this->designButtonManager->updateFont($designSettings['buttons']['font']);
            $this->designButtonManager->updateShape($designSettings['buttons']['shape']);

            // update global contents
            $this->designGlobalContentManager->updateHeaderBackgroundColor(
                $designSettings['global_contents']['header_background_color']
            );
            $this->designGlobalContentManager->updateHasHeaderMargin(
                $designSettings['global_contents']['has_header_margin']
            );
            $this->designGlobalContentManager->updateFooterBackgroundColor(
                $designSettings['global_contents']['footer_background_color']
            );
            $this->designGlobalContentManager->updateCopyrightBackgroundColor(
                $designSettings['global_contents']['copyright_background_color']
            );
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

        if (isset($schema['logos_header']) && is_array($schema['logos_header'])) {
            foreach ($schema['logos_header'] as $attr => $data) {
                $content = $this->globalContentFactory->getHeaderLogos($attr);
                $this->contentBuilder->hydrateContent($content, $data['path']);
            }
        }

        if (isset($schema['logos_footer']) && is_array($schema['logos_footer'])) {
            foreach ($schema['logos_footer'] as $attr => $data) {
                $content = $this->globalContentFactory->getFooterLogos($attr);
                $this->contentBuilder->hydrateContent($content, $data['path']);
            }
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
            if (null === $page->getPublishing()) {
                $page->setPublishing($randomDatetime);
            }

            $page->setState(Page::STATE_ONLINE);
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
