<?php

namespace BackBeeCloud\Structure;

use BackBeeCloud\Job\JobHandlerInterface;
use BackBeePlanet\Job\JobInterface;
use BackBeeCloud\Importer\SimpleWriterInterface;
use BackBeeCloud\Job\YamlStructureDumperJob;

use BackBeePlanet\GlobalSettings;
use BackBeeCloud\Entity\PageType;

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
     * @var \BackBeeCloud\Structure\StructureBuilder
     */
    protected $structureBuilder;

    /**
     * @var \BackBee\Renderer\Helper\GlobalContentFactory
     */
    protected $globalContentFactory;

    protected $folderPath;
    protected $imagePath = '/images/static/theme-default-resources';
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
        $this->structureBuilder = $container->get('cloud.structure_builder');
        $this->globalContentFactory = $container->get('cloud.global_content_factory');
        $this->awsHandler = $container->get('cloud.file_handler');

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
                'theme'       => 'default',
                'logo_header' => $this->getHeaderLogo(),
                'logo_footer' => $this->getFooterLogo(),
                'header'      => [],
                'footer'      => [],
                'pages'       => [],
            ]
        ];

        $i = 0;
        foreach ($pagesType as $pageType) {
            $page = $pageType->getPage();
            $pageObject = $this->computePage($page, $pageType);

            foreach($page->getContentSet()->first() as $cloudContentSet) {
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
            $data['schema']['pages'][$i] = $pageObject;

            $i++;
        }

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
     * @param  String $to      [description]
     * @param  String $fileUrl [description]
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
     * @param  AbstractContent $content [description]
     * @return Array                    [description]
     */
    protected function computeContent(AbstractContent $content)
    {
        $contentObject = [
            'type' => $content->getContentType(),
            'data' => []
        ];

        foreach ($this->structureBuilder->getContentHandlers() as $handler) {
            if ($handler->supports($content)) {

                $handlerData = $handler->handleReverse($content, [
                    'uploadCallback' => [$this, 'uploadImage'],
                    'themeName'      => $this->themeName
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
     * @param  Page     $page     [description]
     * @param  PageType $pageType [description]
     * @return Array              [description]
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
            'contents' => []
        ];
    }

    /**
     * Retrieve the header logo
     *
     * @return String [description]
     */
    protected function getHeaderLogo()
    {
        $headerLogo = $this->globalContentFactory->getHeaderLogo();
        $path = ltrim($headerLogo->path, '/');

        $filename = $this->uploadImage($this->cdnFilePath . '/' . $path);

        return $this->themeName . '/' . $filename;
    }

    /**
     * Retrieve the footer logo
     *
     * @return String [description]
     */
    protected function getFooterLogo()
    {
        $footerLogo = $this->globalContentFactory->getFooterLogo();
        $path = ltrim($footerLogo->path, '/');

        $filename = $this->uploadImage($this->cdnFilePath . '/' . $path);

        return $this->themeName . '/' . $filename;
    }

    /**
     * Define if the page is in footer, header or both menu
     *
     * @param  Page   $page [description]
     * @return String       [description]
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
     * @param  String $imagePath [description]
     * @return String            [description]
     */
    public function uploadImage($imagePath)
    {
        $pathInfo = pathinfo($imagePath);

        if (false === isset($pathInfo['extension'])) {
            return '';
        }

        $imageFilename = $this->getImageFilename($pathInfo['extension']);
        $imageUrl = $imagePath;

        if (1 !== preg_match('#^https?://#', $imageUrl)) {
            $imageUrl = 'http://' . $this->domain . '/' . $imagePath;
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
     * @param  String $extension [description]
     * @return String            [description]
     */
    protected function getImageFilename($extension)
    {
        return $this->themeName . '_' . $this->imageId++ . '.' . $extension;
    }

    /**
     * Compute CloudContentSet for retrieve this data
     *
     * @param  CloudContentSet $cloudContentSet [description]
     * @return Array                            [description]
     */
    protected function computeCloudContentSet(CloudContentSet $cloudContentSet)
    {
        $object = [
            'data' => [
                'parameters' => array_map(function($param) {
                    return $param['value'];
                }, $cloudContentSet->getAllParams()),
            ],
            'columns' => [],
        ];

        if (isset($object['data']['parameters']['bg_image']) && false != $bgImage = $object['data']['parameters']['bg_image']) {
            $object['data']['parameters']['bg_image'] = $this->imagePath . '/' . $this->uploadImage($bgImage);
        }

        return $object;
    }
}
