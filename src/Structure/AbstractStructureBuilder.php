<?php

namespace BackBeeCloud\Structure;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Menu;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractStructureBuilder
{
    use ClassContentHelperTrait;

    const CONTENT_HANDLER_SERVICE_TAG = 'structure.content_handler';

    const MENU_NONE = 'none';
    const MENU_HEADER = 'header';
    const MENU_FOOTER = 'footer';
    const MENU_BOTH = 'both';

    protected $entyMgr;
    protected $pageMgr;
    protected $themeCore;
    protected $contentHandlers;
    protected $globalContentFactory;
    protected $elasticsearchMgr;

    public function __construct(BBApplication $app)
    {
        $this->entyMgr = $app->getEntityManager();
        $this->pageMgr = $app->getContainer()->get('cloud.page_manager');
        $this->globalContentFactory = $app->getContainer()->get('cloud.global_content_factory');
        $this->elasticsearchMgr = $app->getContainer()->get('elasticsearch.manager');
        $this->themeCore = $app->getBundle('theme');

        $this->contentHandlers = [];
        foreach ($app->getContainer()->findTaggedServiceIds(self::CONTENT_HANDLER_SERVICE_TAG) as $id => $data) {
            $this->contentHandlers[] = $app->getContainer()->get($id);
        }
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
            $this->hydrateContent(
                $this->globalContentFactory->getHeaderLogo(),
                ['path' => $schema['logo_header']]
            );
        }

        if (isset($schema['logo_footer'])) {
            $this->hydrateContent(
                $this->globalContentFactory->getFooterLogo(),
                ['path' => $schema['logo_footer']]
            );
        }

        if (isset($schema['header']) && is_array($schema['header'])) {
            foreach ($schema['header'] as $attr => $data) {
                $content = $this->globalContentFactory->getHeaderContent($attr, $data['type']);
                $this->hydrateContent($content, $data['data']);
            }
        }

        if (isset($schema['footer']) && is_array($schema['footer'])) {
            foreach ($schema['footer'] as $attr => $data) {
                $content = $this->globalContentFactory->getFooterContent($attr, $data['type']);
                $this->hydrateContent($content, $data['data']);
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
        if ('home' !== $pageData['type']) {
            if (!isset($pageData['uid'])) {
                $pageData['uid'] = md5($pageData['title']);
            }

            $page = $this->pageMgr->create($pageData);
            $page->setState(Page::STATE_ONLINE);
            $page->setPublishing(new \DateTime());
        } else {
            $site = $this->entyMgr->getRepository(Site::class)->findOneBy([]);
            $page = $this->entyMgr->getRepository(Page::class)->getRoot($site);
            $this->pageMgr->update($page, [
                'type' => $pageData['type'],
            ], false);
        }

        $this->handlePageWithMenu($page, $menu);
        $this->buildContents($page, $contents);

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
    protected function getSchema($name)
    {
        $path = realpath("{$this->basedir()}/{$name}.yml");
        if (false === $path) {
            throw new \InvalidArgumentException("Cannot find structure schema for `{$name}`.");
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Cannot read the file located at `{$path}`.");
        }

        return Yaml::parse(file_get_contents($path));
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

    /**
     * Loops into contents data to inject them into page contentset.
     *
     * @param  Page   $page
     * @param  array  $contents
     */
    protected function buildContents(Page $page, array $contents)
    {
        $this
            ->putContentOnline($page->getContentSet())
            ->putContentOnline($page->getContentSet()->first())
        ;

        $mainContainer = $page->getContentSet()->first();

        $mainContainer->clear();

        foreach ($contents as $data) {
            $cloudContentSet = $this->createContent(CloudContentSet::class);
            $this->hydrateContent($cloudContentSet, isset($data['data']) ? $data['data'] : []);

            $mainContainer->push($cloudContentSet);

            foreach ($data['columns'] as $items) {
                $colContentSet = $this->createContent(ColContentSet::class);
                $cloudContentSet->push($colContentSet);

                foreach ($items as $item) {
                    $content = $this->createContent($item['type']);
                    $colContentSet->push($content);
                    $this->hydrateContent($content, isset($item['data']) ? $item['data'] : []);
                }
            }
        }
    }

    /**
     * Creates a new content according to provided name (could be classcontent type or classname)
     * and updates it to be visible online.
     *
     * @param  string $name
     * @return AbstractClassContent
     */
    protected function createContent($type)
    {
        $content = $this->createOnlineContent($type);
        $this->entyMgr->persist($content);

        return $content;
    }

    public function getContentHandlers()
    {
        return $this->contentHandlers;
    }

    /**
     * Loops into all content handlers to find the handler which can handle the
     * hydratation of provided content with provided data.
     *
     * @param  AbstractClassContent $content
     * @param  array                $data
     * @return self
     */
    protected function hydrateContent(AbstractClassContent $content, array $data)
    {
        foreach ($this->contentHandlers as $handler) {
            if ($handler->supports($content)) {
                $handler->handle($content, $data);
            }
        }

        return $this;
    }

    /**
     * Returns structures base directory.
     *
     * @return string
     */
    abstract protected function basedir();
}
