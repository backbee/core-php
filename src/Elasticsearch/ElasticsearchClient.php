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

namespace BackBeeCloud\Elasticsearch;

use BackBee\BBApplication;
use BackBee\Bundle\Registry;
use BackBee\Config\Config;
use BackBee\Elasticsearch\Config\IndexAnalysisConfigInterface;
use BackBee\Elasticsearch\Config\PageMappingConfigInterface;
use BackBee\Elasticsearch\Config\TagMappingConfigInterface;
use BackBee\Logging\Logger;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Symfony\Component\Console\Style\SymfonyStyle;
use function in_array;

/**
 * Class ElasticsearchClient
 *
 * @package BackBeeCloud\Elasticsearch
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ElasticsearchClient
{
    public const INDEX_BASE_NAME = 'backbee_standalone_';
    public const DEFAULT_ANALYZER = 'standard';
    public const ELASTICSEARCH_INDEX_NAME = 'backbee';

    /**
     * @var BBApplication
     */
    protected $bbApp;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var EntityManager
     */
    protected $entityMgr;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \BackBee\Elasticsearch\Config\IndexAnalysisConfigInterface
     */
    private IndexAnalysisConfigInterface $indexAnalysisConfig;

    /**
     * @var \BackBee\Elasticsearch\Config\PageMappingConfigInterface
     */
    private PageMappingConfigInterface $pageMappingConfig;

    /**
     * @var \BackBee\Elasticsearch\Config\TagMappingConfigInterface
     */
    private TagMappingConfigInterface $tagMappingConfig;

    /**
     * Constructor.
     *
     * @param \BackBee\BBApplication $bbApp
     * @param \BackBee\Config\Config $config
     */
    public function __construct(BBApplication $bbApp, Config $config)
    {
        $this->bbApp = $bbApp;
        $this->entityMgr = $bbApp->getEntityManager();
        $this->settings = $config->getSection('elasticsearch');
        $this->logger = $bbApp->getLogging();
        $this->indexAnalysisConfig = $bbApp->getContainer()->get('core.elasticsearch.index_analysis.config');
        $this->pageMappingConfig = $bbApp->getContainer()->get('core.elasticsearch.page_mapping.config');
        $this->tagMappingConfig = $bbApp->getContainer()->get('core.elasticsearch.tag_mapping.config');
    }

    /**
     * Returns an instance of Elasticsearch PHP client.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = ClientBuilder::create()->setHosts([$this->settings['host']])->build();
        }

        return $this->client;
    }

    /**
     * To define a main analyzer for the whole application, you must use this
     * method. It allows you to define the right analyzer to use against the
     * provided language.
     *
     * @param string $lang The site main language
     *
     * @return self
     * @throws OptimisticLockException
     */
    public function initMainAnalyzer(string $lang): self
    {
        $analyzer = $lang;
        if (!in_array($lang, $this->settings['supported_analyzer'], true)) {
            $analyzer = self::DEFAULT_ANALYZER;
        }

        if ($registry = ($this->getAnalyzerRegistry() === null)) {
            $registry = new Registry();
            $registry->setKey('analyzer');
            $registry->setScope('ELASTICSEARCH');

            $this->entityMgr->persist($registry);
        }

        $registry->setValue($analyzer);
        $this->entityMgr->flush($registry);

        return $this;
    }

    /**
     * Creates an index according to current application/site main language.
     *
     * @return self
     */
    public function createIndex(): self
    {
        if ($this->getClient()->indices()->exists(['index' => $this->getIndexName()])) {
            return $this;
        }

        $this->getClient()->indices()->create(
            [
                'index' => $this->getIndexName(),
                'body' => [
                    'settings' => [
                        'number_of_shards' => $this->settings['index']['number_of_shards'],
                        'number_of_replicas' => $this->settings['index']['number_of_replicas'],
                        'max_result_window' => 50000,
                        'analysis' => $this->indexAnalysisConfig->toArray(),
                    ],
                ],
            ]
        );

        return $this;
    }

    /**
     * Creates page and tag types in the right index for current application/site.
     *
     * @return self
     */
    final public function createTypes(): self
    {
        $this->createCoreTypes();
        $this->createCustomTypes();

        return $this;
    }

    /**
     * Indexes the provide page into the 'page' type.
     *
     * @param Page $page
     *
     * @return self
     */
    final public function indexPage(Page $page): self
    {
        $pageDocument = [
            'index' => $this->getIndexName(),
            'id' => $page->getUid(),
            'body' => $this->buildPageDocument($page),
        ];

        $this->getClient()->index($pageDocument);

        return $this;
    }

    /**
     * Build page document.
     *
     * @param \BackBee\NestedNode\Page $page
     *
     * @return array
     */
    private function buildPageDocument(Page $page): array
    {
        return array_merge(
            [
                'title' => $page->getTitle(),
                'tags' => [],
                'contents' => '',
                'is_online' => $page->isOnline(),
                'modified_at' => $page->getModified()->format('Y-m-d H:i:s'),
                'has_draft_contents' => false,
                'source' => Page::SOURCE_TYPE,
            ],
            $this->getPageCustomDataToIndex($page)
        );
    }

    private function buildTagDocument(Tag $tag): array
    {
        return array_merge(
            [
                'name' => $tag->getKeyWord(),
                'source' => Tag::SOURCE_TYPE,
            ],
            $this->getTagCustomDataToIndex($tag)
        );
    }

    /**
     * Gets all pages of current application and index these.
     *
     * @param bool              $memoryHardCleanup
     * @param SymfonyStyle|null $output
     * @param int               $batchSize
     *
     * @return self
     * @see ::indexPage
     */
    public function indexAllPages(
        bool $memoryHardCleanup = false,
        ?SymfonyStyle $output = null,
        int $batchSize = 1000
    ): self {
        $params = ['body' => []];
        $docCount = 0;

        $this->getClient()->indices()->putSettings([
            'index' => $this->getIndexName(),
            'body' => ['refresh_interval' => '-1'],
        ]);

        foreach ($this->entityMgr->getRepository(Page::class)->getAllPages() as $page) {
            if ($page->getState() === Page::STATE_DELETED) {
                $params['body'][] = [
                    'delete' => [
                        '_index' => $this->getIndexName(),
                        '_id' => $page->getUid(),
                    ],
                ];
            } else {
                $params['body'][] = [
                    'index' => [
                        '_index' => $this->getIndexName(),
                        '_id' => $page->getUid(),
                    ],
                ];
                $params['body'][] = $this->buildPageDocument($page);
            }

            $docCount++;

            if ($docCount % $batchSize === 0) {
                $this->flushBulk($params, $docCount, $output);
                $params = ['body' => []];
                $docCount = 0;

                if ($memoryHardCleanup) {
                    $this->entityMgr->clear();
                    gc_collect_cycles();
                }
            }
        }

        if (!empty($params['body'])) {
            $this->flushBulk($params, $docCount, $output);
        }

        $this->getClient()->indices()->putSettings([
            'index' => $this->getIndexName(),
            'body' => ['refresh_interval' => '1s'],
        ]);

        $this->getClient()->indices()->refresh(['index' => $this->getIndexName()]);

        return $this;
    }

    /**
     * Indexes the provided tag into Elasticsearch.
     *
     * @param Tag $tag
     *
     * @return self
     */
    final public function indexTag(Tag $tag): self
    {
        $this->getClient()->index(
            [
                'index' => $this->getIndexName(),
                'id' => $tag->getUid(),
                'body' => $this->buildTagDocument($tag),
            ]
        );

        return $this;
    }

    /**
     * Indexes all tags into Elasticsearch except the root keyword.
     *
     * @param SymfonyStyle|null $output
     * @param int               $batchSize
     *
     * @return self
     */
    public function indexAllTags(?SymfonyStyle $output = null, int $batchSize = 1000): self
    {
        $rootUid = md5('root');
        $params = ['body' => []];
        $docCount = 0;

        foreach ($this->entityMgr->getRepository(Tag::class)->getAllTags() as $tag) {
            if ($rootUid === $tag->getUid()) {
                continue;
            }

            $params['body'][] = [
                'index' => [
                    '_index' => $this->getIndexName(),
                    '_id' => $tag->getUid(),
                ],
            ];
            $params['body'][] = $this->buildTagDocument($tag);

            $docCount++;

            if ($docCount % $batchSize === 0) {
                $this->flushBulk($params, $docCount, $output);
                $params = ['body' => []];
                $docCount = 0;
            }
        }

        if (!empty($params['body'])) {
            $this->flushBulk($params, $docCount, $output);
        }

        return $this;
    }

    /**
     * Returns the right index name of current application.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return (new Slugify())->slugify(
            $this->settings['index_name'] ?? (self::INDEX_BASE_NAME . $this->getSiteName())
        );
    }

    /**
     * Returns the right type of current application according to provided custom type name.
     *
     * @param string $type
     *
     * @return string
     */
    public function getCustomTypeName(string $type): string
    {
        return $this->getSiteName() . '_' . $type;
    }

    /**
     * Returns the right tag type of current application.
     *
     * @return string
     */
    public function getTagTypeName(): string
    {
        return $this->getCustomTypeName('tag');
    }

    /**
     * Create core types.
     */
    protected function createCoreTypes(): void
    {
        $this->getClient()->indices()->putMapping(
            [
                'index' => $this->getIndexName(),
                'body' => [
                    '_source' => [
                        'enabled' => true,
                    ],
                    'properties' => $this->pageMappingConfig->getProperties(),
                ],
            ]
        );

        $this->getClient()->indices()->putMapping(
            [
                'index' => $this->getIndexName(),
                'body' => [
                    '_source' => [
                        'enabled' => true,
                    ],
                    'properties' => array_merge(
                        $this->tagMappingConfig->getProperties(),
                        $this->getCustomTagTypeProperties(),
                    ),
                ],
            ]
        );
    }

    /**
     * Override this method if you want to add custom properties for page type.
     *
     * @return array
     */
    protected function getCustomPageTypeProperties(): array
    {
        return [];
    }

    /**
     * Override this method if you want to add custom properties for tag type.
     *
     * @return array
     */
    protected function getCustomTagTypeProperties(): array
    {
        return [];
    }

    /**
     * Override this method if you want to create your own types.
     */
    protected function createCustomTypes(): void
    {
    }

    /**
     * Override this method if you want to index some custom property for page.
     *
     * @param Page $page
     *
     * @return array
     */
    protected function getPageCustomDataToIndex(Page $page): array
    {
        return [];
    }

    /**
     * Override this method if you want to index some custom property for tag.
     *
     * @param Tag $tag
     *
     * @return array
     */
    protected function getTagCustomDataToIndex(Tag $tag): array
    {
        return [];
    }

    /**
     * Returns the name of current application's index.
     *
     * @return string
     */
    protected function getSiteName(): string
    {
        $site = $this->entityMgr->getRepository(Site::class)->findOneBy([]);

        return $site ? $site->getLabel() : '';
    }

    /**
     * Returns the registry that contains the custom analyzer to use. It can be
     * null if current application has no settings.
     *
     * @return object|null
     */
    protected function getAnalyzerRegistry(): ?Registry
    {
        return $this->entityMgr->getRepository(Registry::class)->findOneBy(
            [
                'key' => 'analyzer',
                'scope' => 'ELASTICSEARCH',
            ]
        );
    }

    /**
     * Flush bulk.
     *
     * @param array                                              $params
     * @param int                                                $docCount
     * @param null|\Symfony\Component\Console\Style\SymfonyStyle $output
     *
     * @return void
     */
    private function flushBulk(array $params, int $docCount, ?SymfonyStyle $output): void
    {
        if (empty($params['body'])) {
            return;
        }

        $response = $this->getClient()->bulk($params);

        if ($response['errors'] ?? false) {
            foreach ($response['items'] as $item) {
                $action = array_key_first($item);
                if (isset($item[$action]['error'])) {
                    $this->logger->error(
                        \sprintf(
                            '%s::%s — Bulk error [%s] id=%s : %s',
                            __CLASS__,
                            __FUNCTION__,
                            $action,
                            $item[$action]['_id'],
                            $item[$action]['error']['reason'] ?? 'unknown'
                        )
                    );
                }
            }
        }

        if ($output) {
            $output->progressAdvance($docCount);
        }
    }
}
