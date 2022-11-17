<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBee\KnowledgeGraph;

use BackBee\BBApplication;
use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\KnowledgeGraph\Schema\SchemaContext;
use BackBee\MetaData\MetaDataBag;
use BackBee\NestedNode\Page;
use BackBeeCloud\Elasticsearch\ElasticsearchManager;
use BackBeeCloud\MultiLang\MultiLangManager;
use BackBeeCloud\MultiLang\PageAssociationManager;
use BackBeeCloud\SearchEngine\SearchEngineManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

/**
 * Class SeoMetadataManager
 *
 * @package BackBee\KnowledgeGraph
 *
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class SeoMetadataManager
{
    /**
     * @var BBApplication
     */
    private BBApplication $bbApp;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SearchEngineManager
     */
    private SearchEngineManager $searchEngineManager;

    /**
     * @var ElasticsearchManager
     */
    private ElasticsearchManager $elasticsearchManager;

    /**
     * @var PageAssociationManager
     */
    private PageAssociationManager $pageAssociationManager;

    /**
     * @var MultiLangManager
     */
    private MultiLangManager $multiLangManager;

    /**
     * @var array
     */
    private array $seoData;

    /**
     * @var MetaDataBag|array
     */
    private $pageMetadataBag;

    /**
     * @var null|array
     */
    private ?array $esResult;

    /**
     * SeoMetadataManager constructor.
     *
     * @param BBApplication          $bbApp
     * @param EntityManagerInterface $entityManager
     * @param SearchEngineManager    $searchEngineManager
     * @param ElasticsearchManager   $elasticsearchManager
     * @param MultiLangManager       $multiLangManager
     * @param PageAssociationManager $pageAssociationManager
     */
    public function __construct(
        BBApplication $bbApp,
        EntityManagerInterface $entityManager,
        SearchEngineManager $searchEngineManager,
        ElasticsearchManager $elasticsearchManager,
        MultiLangManager $multiLangManager,
        PageAssociationManager $pageAssociationManager
    ) {
        $this->bbApp = $bbApp;
        $this->entityManager = $entityManager;
        $this->searchEngineManager = $searchEngineManager;
        $this->elasticsearchManager = $elasticsearchManager;
        $this->multiLangManager = $multiLangManager;
        $this->pageAssociationManager = $pageAssociationManager;
        $this->seoData = [];
        $this->pageMetadataBag = [];
        $this->esResult = [];
    }

    /**
     * Get page seo metadata.
     *
     * @param Page $page
     *
     * @return array
     */
    public function getPageSeoMetadata(Page $page): array
    {
        $this->pageMetadataBag = $page->getMetaData() ?: new MetaDataBag();

        $this
            ->setHost()
            ->setMetadata()
            ->getElasticSearchResult($page->getUid());

        if ($this->esResult) {
            $this
                ->setTitle()
                ->setDescription()
                ->setImageUrl()
                ->setSearchEngineOptions();
        }

        return $this->seoData;
    }

    /**
     * Get render seo metadata.
     *
     * @param Page          $page
     * @param SchemaContext $schemaContext
     *
     * @return string
     */
    public function getRenderSeoMetadata(Page $page, SchemaContext $schemaContext): string
    {
        // global metadata
        $metadata = $this->getPageSeoMetadata($page);

        $params = [
            'metadata' => $metadata,
            'metadata_robots' => $this->searchEngineManager->getMetadataRobots($metadata),
            'schema_context' => $schemaContext,
        ];

        // multi lang equivalent pages
        if ($this->multiLangManager->isActive()) {
            $params['multilang_equivalent_pages'] = $this->pageAssociationManager->getEquivalentPagesData(
                $page,
                $this->bbApp->getBBUserToken()
            );
        }

        //dump($params);

        return $this->bbApp->getRenderer()->partial('KnowledgeGraph/seoMetadata.html.twig', $params);
    }

    /**
     * Get value without forbidden characters.
     *
     * @param string $value
     *
     * @return string
     */
    public function getValueWithoutForbiddenCharacters(string $value): string
    {
        $symbols = "\x{1F100}-\x{1F1FF}" // Enclosed Alphanumeric Supplement
            . "\x{1F300}-\x{1F5FF}" // Miscellaneous Symbols and Pictographs
            . "\x{1F600}-\x{1F64F}" //Emoticons
            . "\x{1F680}-\x{1F6FF}" // Transport And Map Symbols
            . "\x{1F900}-\x{1F9FF}" // Supplemental Symbols and Pictographs
            . "\x{2600}-\x{26FF}" // Miscellaneous Symbols
            . "\x{2700}-\x{27BF}"; // Dingbats

        return trim(preg_replace('/[' . $symbols . ']+/u', '', $value));
    }

    /**
     * Set host.
     *
     * @return $this
     */
    private function setHost(): self
    {
        $this->seoData['host'] = $this->bbApp->getRequest()->getHost();

        return $this;
    }

    /**
     * Get metadata.
     *
     * @return $this
     */
    private function setMetadata(): self
    {
        foreach ($this->pageMetadataBag as $attr => $metadata) {
            if (($metadata->getAttribute('name') === $attr) && $value = $metadata->getAttribute('content')) {
                $this->seoData[(string)$attr] = $value;
            }
        }

        return $this;
    }

    /**
     * Set title.
     *
     * @return $this
     */
    private function setTitle(): self
    {
        $this->seoData['title'] = $this->seoData['title'] ?? $this->esResult['_source']['title'];

        return $this;
    }

    /**
     * Get elastic search result.
     *
     * @param string $pageUid
     *
     * @return void
     */
    private function getElasticSearchResult(string $pageUid): void
    {
        try {
            $this->esResult = $this->elasticsearchManager->getClient()->get(
                [
                    'id' => $pageUid,
                    'index' => $this->elasticsearchManager->getIndexName(),
                    '_source' => ['title', 'abstract_uid', 'type', 'image_uid'],
                ]
            );
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->warning(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
            $this->esResult = null;
        }
    }

    /**
     * Set description.
     *
     * @return $this
     */
    public function setDescription(): self
    {
        try {
            if (($this->seoData['description'] ?? null) === null &&
                $this->esResult['_source']['abstract_uid'] &&
                $abstract = $this->entityManager->find(
                    ArticleAbstract::class,
                    $this->esResult['_source']['abstract_uid']
                )
            ) {
                $this->seoData['description'] = $this->getValueWithoutForbiddenCharacters(
                    mb_substr(
                        trim(
                            html_entity_decode(
                                strip_tags(
                                    preg_replace(
                                        ['#<[^>]+>#', '#\s\s+#', '#&nbsp;#', '/\\\\n/', '#"#'],
                                        [' ', ' ', '', ''],
                                        $abstract->value
                                    )
                                )
                            )
                        ),
                        0,
                        300
                    )
                );
            }
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $this;
    }

    /**
     * Set image url.
     *
     * @return $this
     */
    public function setImageUrl(): self
    {
        try {
            $image = $this->entityManager->find(
                Image::class,
                $this->esResult['_source']['image_uid']
            );

            if ($this->esResult['_source']['image_uid'] && $image) {
                $this->seoData['image_url'] = $image->image->path;
            } else {
                $knowledgeGraphConfig = $this->bbApp->getConfig()->getSection('knowledge_graph');
                $this->seoData['image_url'] = $knowledgeGraphConfig['graph']['image'] ??
                    $knowledgeGraphConfig['graph']['logo'];
            }
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $this;
    }

    /**
     * Set search engine options.
     *
     * @return void
     */
    private function setSearchEngineOptions(): void
    {
        $searchEngine = $this->searchEngineManager->googleSearchEngineIsActivated();

        $this->seoData['index'] = $this->pageMetadataBag->get('index') === null ?
            $searchEngine : $this->pageMetadataBag->get('index')->getAttribute('content');
        $this->seoData['follow'] = $this->pageMetadataBag->get('follow') === null ?
            $searchEngine : $this->pageMetadataBag->get('follow')->getAttribute('content');
    }
}
