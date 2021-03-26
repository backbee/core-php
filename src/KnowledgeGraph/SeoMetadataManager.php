<?php

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
use Doctrine\ORM\EntityManager;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Exception;
use function strlen;

/**
 * Class SeoMetadataManager
 *
 * @package BackBee\KnowledgeGraph
 */
class SeoMetadataManager
{
    /**
     * @var BBApplication
     */
    private $bbApp;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var SearchEngineManager
     */
    private $searchEngineManager;

    /**
     * @var ElasticsearchManager
     */
    private $elasticsearchManager;

    /**
     * @var PageAssociationManager
     */
    private $pageAssociationManager;

    /**
     * @var MultiLangManager
     */
    private $multiLangManager;

    /**
     * @var array
     */
    private $seoData;

    /**
     * @var MetaDataBag|array
     */
    private $pageMetadataBag;

    /**
     * @var array
     */
    private $esResult;

    /**
     * SeoMetadataManager constructor.
     *
     * @param BBApplication          $bbApp
     * @param EntityManager          $entityManager
     * @param SearchEngineManager    $searchEngineManager
     * @param ElasticsearchManager   $elasticsearchManager
     * @param MultiLangManager       $multiLangManager
     * @param PageAssociationManager $pageAssociationManager
     */
    public function __construct(
        BBApplication $bbApp,
        EntityManager $entityManager,
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
        $this->pageMetadataBag = $page->getMetaData() ?: [];

        $this
            ->setMetadata()
            ->getElasticSearchResult($page->getUid());

        if ($this->esResult) {
            $this
                ->setTitle()
                ->setDescription()
                ->setSearchEngineOptions();

            if ('article' === $this->esResult['_source']['type']) {
                $this->setImageUrl();
            }
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

        return $this->bbApp->getRenderer()->partial('KnowledgeGraph/seoMetadata.html.twig', $params);
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
     * @return $this
     */
    private function getElasticSearchResult(string $pageUid): self
    {
        try {
            $this->esResult = $this->elasticsearchManager->getClient()->get(
                [
                    'id' => $pageUid,
                    'index' => $this->elasticsearchManager->getIndexName(),
                    '_source' => ['title', 'abstract_uid', 'type', 'image_uid'],
                ]
            );
        } catch (Missing404Exception $exception) {
            $this->esResult = null;
        }

        return $this;
    }

    /**
     * Set description.
     *
     * @return $this
     */
    private function setDescription(): self
    {
        try {
            if ($this->seoData['description'] &&
                $this->esResult['source']['abstract_uid'] &&
                $abstract = $this->entityManager->find(
                    ArticleAbstract::class,
                    $this->esResult['source']['abstract_uid']
                )
            ) {
                $this->seoData['description'] = strip_tags(
                    str_replace(
                        "\n",
                        '',
                        str_replace(
                            '&nbsp;',
                            '',
                            strlen($abstract->value) > 300 ? mb_substr(
                                    $abstract->value,
                                    0,
                                    300
                                ) . '...' : $abstract->value
                        )
                    )
                );
            }
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $this;
    }

    /**
     * Set image url.
     *
     * @return $this
     */
    private function setImageUrl(): self
    {
        try {
            if (
                $this->esResult['_source']['image_uid'] &&
                $image = $this->entityManager->find(Image::class, $this->esResult['_source']['image_uid'])
            ) {
                $this->seoData['image_url'] = $image->image->path;
            }
        } catch (Exception $exception) {
            $this->bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        return $this;
    }

    /**
     * Set search engine options.
     *
     * @return $this
     */
    private function setSearchEngineOptions(): self
    {
        $searchEngine = $this->searchEngineManager->googleSearchEngineIsActivated();

        $this->seoData['index'] = null === $this->pageMetadataBag->get('index') ?
            $searchEngine : $this->pageMetadataBag->get('index')->getAttribute('content');
        $this->seoData['follow'] = null === $this->pageMetadataBag->get('follow') ?
            $searchEngine : $this->pageMetadataBag->get('follow')->getAttribute('content');

        return $this;
    }
}
