<?php

namespace BackBeeCloud\Structure;

use BackBeeCloud\Entity\PageType;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\JobHandlerInterface;
use BackBeeCloud\Job\YamlStructureDumperJob;
use BackBeeCloud\ThemeColor\Color;
use BackBeePlanet\GlobalSettings;
use BackBeePlanet\Job\JobInterface;
use BackBee\BBApplication;
use BackBee\ClassContent\AbstractContent;
use BackBee\ClassContent\CloudContentSet;
use BackBee\NestedNode\KeyWord;
use BackBee\NestedNode\Page;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class YamlStructureDumperManager implements JobHandlerInterface
{
    /**
     * @var \BackBee\BBApplication
     */
    protected $app;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \BackBeeCloud\Entity\PageManager
     */
    protected $pageMgr;

    /**
     * @var \BackBeeCloud\Structure\ContentBuilder
     */
    protected $contentBuilder;

    /**
     * @var \BackBee\Renderer\Helper\GlobalContentFactory
     */
    protected $globalContentFactory;

    /**
     * @var \BackBeeCloud\Design\ButtonManager
     */
    protected $designButtonManager;

    /**
     * @var \BackBeeCloud\Design\GlobalContentManager
     */
    protected $designGlobalContentManager;

    /**
     * @var \BackBeeCloud\ThemeColor\ColorPanelManager
     */
    protected $designColorPanelManager;

    /**
     * @var \BackBeeCloud\ThemeColor\ThemeColorManager
     */
    protected $designThemeColorManager;

    protected $folderPath;
    protected $imagePath = '/static/theme-default-resources';
    protected $imageFolderPath;

    protected $imageId = 1;

    protected $zip;

    protected $cdnFilePath;

    public function __construct(BBApplication $app)
    {
        $container = $app->getContainer();

        $this->app = $app;
        $this->em = $app->getEntityManager();
        $this->pageMgr = $container->get('cloud.page_manager');
        $this->contentBuilder = $container->get('cloud.structure.content_builder');
        $this->globalContentFactory = $container->get('cloud.global_content_factory');
        $this->awsHandler = $container->get('cloud.file_handler');
        $this->designButtonManager = $app->getContainer()->get('cloud.design.button.manager');
        $this->designGlobalContentManager = $app->getContainer()->get('cloud.design.global.content.manager');
        $this->designColorPanelManager = $app->getContainer()->get('cloud.color_panel.manager');
        $this->designThemeColorManager = $app->getContainer()->get('cloud.theme_color.manager');

        $cdnSettings = (new GlobalSettings())->cdn();
        $this->cdnFilePath = $cdnSettings['image_domain'];
    }

    public function handle(JobInterface $job, SimpleWriterInterface $writer)
    {
        $this->domain = $job->domain();
        $this->themeName = $job->themeName();

        $writer->write('<c2>Start build yaml of ' . $this->themeName . '</c2>');

        $this->imagePath = $this->imagePath  . '/' . $this->themeName;
        $rootPath = sys_get_temp_dir() . '/ ' . time() . '/';
        $this->folderPath = sprintf('%s/%s', $rootPath, $this->themeName);
        $this->imageFolderPath = $this->folderPath . '/' . $this->themeName;

        mkdir($rootPath);
        mkdir($this->folderPath);
        mkdir($this->imageFolderPath);

        $this->zip = new \ZipArchive();
        $fileZipName = 'structure.zip';
        $fileZiPath = sprintf('%s/%s', $rootPath, $fileZipName);

        if ($this->zip->open($fileZiPath, \ZipArchive::CREATE) !== true) {
            $writer.write('<warn>Problem when opening the zip file</warn>');
            return;
        }

        $pagesType = $this->em->getRepository('BackBeeCloud\Entity\PageType')->findAll();

        $data = [
            'schema' => [
                'design_settings' => $this->getDesignSettings(),
                'logo_header' => $this->getHeaderLogo(),
                'logo_footer' => $this->getFooterLogo(),
                'header'      => [],
                'footer'      => [
                    'social' => $this->computeContent(
                        $this->globalContentFactory->getFooterContent('social', 'Social/Icons')
                    ),
                    'address' => $this->computeContent(
                        $this->globalContentFactory->getFooterContent('address', 'Text/Paragraph')
                    ),
                    'copyright' => $this->computeContent(
                        $this->globalContentFactory->getFooterContent('copyright', 'Text/Paragraph')
                    ),
                ],
                'pages'       => [],
            ]
        ];

        foreach ($pagesType as $pageType) {
            if (!$pageType->getType()->isDumpable()) {
                continue;
            }

            $page = $pageType->getPage();
            $pageObject = $this->computePage($page, $pageType);

            foreach($page->getContentSet()->first() as $cloudContentSet) {
                if (!($cloudContentSet instanceof CloudContentSet)) {
                    continue;
                }

                $cloudContentSetObject = $this->computeCloudContentSet($cloudContentSet);

                $x = 0;
                foreach ($cloudContentSet as $colContentSet) {
                    foreach ($colContentSet as $content) {
                        $cloudContentSetObject['columns'][$x][] = $this->computeContent($content);
                    }

                    $x++;
                }

                $pageObject['contents'][] = $cloudContentSetObject;
            }

            $data['schema']['pages'][$page->getUid()] = $pageObject;
        }

        $headerMenu = $this->globalContentFactory->getHeaderMenu();
        foreach (array_reverse($headerMenu->getParamValue('items')) as $item) {
            if (isset($data['schema']['pages'][$item['id']])) {
                $pageData = $data['schema']['pages'][$item['id']];
                unset($data['schema']['pages'][$item['id']]);
                array_unshift($data['schema']['pages'], $pageData);
            }
        }

        $data['schema']['pages'] = array_values($data['schema']['pages']);

        $filename = 'structure.yml';
        touch($tmpfile = sprintf('%s/%s', $this->folderPath, $filename));
        file_put_contents($tmpfile, Yaml::dump($data, 30, 2));

        $this->zip->addFile($tmpfile);

        $this->zip->close();

        $url = $this->cdnFilePath . '/'. $this->awsHandler->upload('structure/'. $fileZipName, $fileZiPath, true);

        $this->sendMail($job->mail(), $url);

        $writer->write('<c2>Url: ' . $url . '</c2>');
        $writer->write('<c2>Build build yaml of ' . $this->themeName . ' finish !</c2>');
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job)
    {
        return $job instanceof YamlStructureDumperJob;
    }

    /**
     * Send mail with the package uploaded in AWS3
     *
     * @param  string $to
     * @param  string $fileUrl
     */
    protected function sendMail($to, $fileUrl)
    {
        $mailerConfig = (new GlobalSettings())->mailer();

        $text = str_replace('http://', '', $fileUrl);

        $message = \Swift_Message::newInstance()
            ->setSubject('New theme ' . $this->themeName . ' uploaded !')
            ->setFrom($mailerConfig['email_from'])
            ->setTo($to)
            ->setBody($text, 'text/html');

        $transport = \Swift_SmtpTransport::newInstance($mailerConfig['server'], $mailerConfig['port'], $mailerConfig['encryption']);

        $transport->setUsername($mailerConfig['username'])->setPassword($mailerConfig['password']);

        \Swift_Mailer::newInstance($transport)->send($message);
    }

    /**
     * Compute content for retrieve this data
     *
     * @param  AbstractContent $content
     *
     * @return array
     */
    protected function computeContent(AbstractContent $content)
    {
        $contentObject = [
            'type' => $content->getContentType(),
            'data' => []
        ];

        foreach ($this->contentBuilder->getContentHandlers() as $handler) {
            if ($handler->supports($content)) {

                $handlerData = $handler->handleReverse($content, [
                    'current_data'   => $contentObject['data'],
                    'uploadCallback' => [$this, 'uploadImage'],
                    'themeName'      => $this->themeName,
                ]);

                if (false != $handlerData) {
                    $contentObject['data'] = $handlerData;
                }
            }
        }

        return $contentObject;
    }

    /**
     * Compute page for retrieve this data
     *
     * @param  Page     $page
     * @param  PageType $pageType
     *
     * @return array
     */
    protected function computePage(Page $page, PageType $pageType)
    {
        return [
            'menu'  => $this->getMenuState($page),
            'title' => $page->getTitle(),
            'tags'  => array_map(function(KeyWord $keyword) {
                return $keyword->getKeyWord();
            }, $this->pageMgr->getPageTag($page)->getTags()->toArray()),
            'type'  => $pageType->getTypeName(),
            'contents' => [],
        ];
    }

    /**
     * Retrieve the header logo
     *
     * @return string
     */
    protected function getHeaderLogo()
    {
        $headerLogo = $this->globalContentFactory->getHeaderLogo();
        $path = ltrim($headerLogo->image->path, '/');

        $filename = $this->uploadImage($this->cdnFilePath . '/' . $path);

        return $this->themeName . '/' . $filename;
    }

    /**
     * Retrieve the footer logo
     *
     * @return string
     */
    protected function getFooterLogo()
    {
        $footerLogo = $this->globalContentFactory->getFooterLogo();
        $path = ltrim($footerLogo->image->path, '/');

        $filename = $this->uploadImage($this->cdnFilePath . '/' . $path);

        return $this->themeName . '/' . $filename;
    }

    /**
     * Define if the page is in footer, header or both menu
     *
     * @param  Page   $page
     *
     * @return string
     */
    protected function getMenuState(Page $page)
    {
        $headerMenu = $this->globalContentFactory->getHeaderMenu();
        $footerMenu = $this->globalContentFactory->getFooterMenu();

        $inHeader = false;
        $inFooter = false;

        foreach($headerMenu->getParamValue('items') as $item) {
            if ($item['id'] === $page->getUid()) {
                $inHeader = true;
            }
        }

        foreach($footerMenu->getParamValue('items') as $item) {
            if ($item['id'] === $page->getUid()) {
                $inFooter = true;
            }
        }

        return $inFooter && $inHeader ? 'both' : ($inFooter ? 'footer' : ($inHeader ? 'header' : 'none'));
    }

    /**
     * Upload image to local and add in the zip file
     *
     * @param  string $imagePath
     *
     * @return string
     */
    public function uploadImage($imagePath)
    {
        $pathInfo = pathinfo($imagePath);

        if (false === isset($pathInfo['extension'])) {
            return '';
        }

        $imageFilename = $this->getImageFilename($pathInfo['extension']);
        $imageUrl = $imagePath;

        if (1 !== preg_match('#^(https?:)?//#', $imageUrl)) {
            $imageUrl = $this->cdnFilePath . $imagePath;
        }

        if (1 === preg_match('#^//#', $imageUrl)) {
            $imageUrl = 'https:' . $imageUrl;
        }

        if (false !== $rawContent = file_get_contents($imageUrl)) {
            touch($tmpfile = sprintf('%s/%s', $this->imageFolderPath, $imageFilename));
            file_put_contents($tmpfile, $rawContent);

            $this->zip->addFile($tmpfile);
        }

        return $imageFilename;
    }

    /**
     * Retrieve the image file name
     *
     * @param  string $extension
     *
     * @return string
     */
    protected function getImageFilename($extension)
    {
        return $this->themeName . '_' . $this->imageId++ . '.' . $extension;
    }

    /**
     * Compute CloudContentSet for retrieve this data
     *
     * @param  CloudContentSet $cloudContentSet
     *
     * @return array
     */
    protected function computeCloudContentSet(CloudContentSet $cloudContentSet)
    {
        $params = $cloudContentSet->getAllParams();
        foreach ($cloudContentSet->getDefaultParams() as $attr => $default) {
            if (
                $params[$attr]['value'] === $default['value']
                || (
                    in_array($attr, ['responsive_mobile', 'responsive_tablet'])
                    && false == $params[$attr]['value']
                )
            ) {
                unset($params[$attr]);
            }
        }

        $object = [
            'data' => [
                'parameters' => array_map(function($param) {
                    return $param['value'];
                }, $params),
            ],
            'columns' => [],
        ];

        if (
            isset($object['data']['parameters']['bg_image'])
            && false != $bgImage = $object['data']['parameters']['bg_image']
        ) {
            $object['data']['parameters']['bg_image'] = $this->imagePath . '/' . $this->uploadImage($bgImage);
        }

        return $object;
    }

    protected function getDesignSettings()
    {
        $colorPanel = $this->designColorPanelManager->getColorPanel();

        return [
            'theme_color' => $this->designThemeColorManager->getByColorPanel($colorPanel)->getUniqueName(),
            'color_panel' => [
                'primary' => $colorPanel->getPrimaryColor()->getColor(),
                'custom_colors' => array_map(function (Color $color) {
                    return ['color' => $color->getColor()];
                }, (array) $colorPanel->getCustomColors()),
            ],
            'buttons' => $this->designButtonManager->getSettings(),
            'global_contents' => $this->designGlobalContentManager->getSettings(),
        ];
    }
}
