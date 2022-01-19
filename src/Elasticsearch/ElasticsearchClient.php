<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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
use BackBee\Logging\Logger;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Exception;
use Generator;
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
     * ElasticsearchManager constructor.
     *
     * @param BBApplication $bbApp
     * @param Config        $config
     */
    public function __construct(BBApplication $bbApp, Config $config)
    {
        $this->bbApp = $bbApp;
        $this->entityMgr = $bbApp->getEntityManager();
        $this->settings = $config->getSection('elasticsearch');
        $this->logger = $bbApp->getLogging();
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
                        'analysis' => [
                            'filter' => [
                                'autocomplete_filter' => [
                                    'type' => 'edge_ngram',
                                    'min_gram' => 1,
                                    'max_gram' => 20,
                                ],
                                'my_ascii_folding' => [
                                    'type' => 'asciifolding',
                                    'preserve_original' => true,
                                ],
                                'my_stemmer_french' => [
                                    'type' => 'stemmer',
                                    'language' => 'french',
                                ],
                                'my_stemmer_english' => [
                                    'type' => 'stemmer',
                                    'language' => 'english',
                                ],
                                'my_french_elision' => [
                                    'type' => 'elision',
                                    'articles_case' => true,
                                    'articles' => [
                                        0 => 'l',
                                        1 => 'm',
                                        2 => 't',
                                        3 => 'qu',
                                        4 => 'n',
                                        5 => 's',
                                        6 => 'j',
                                        7 => 'd',
                                        8 => 'c',
                                        9 => 'jusqu',
                                        10 => 'quoiqu',
                                        11 => 'lorsqu',
                                        12 => 'puisqu',
                                    ],
                                ],
                            ],
                            'analyzer' => [
                                'std_folded' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => [
                                        'lowercase',
                                        'asciifolding',
                                        'my_stemmer_english',
                                        'my_french_elision',
                                        'my_stemmer_french',
                                    ],
                                ],
                                'autocomplete' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => [
                                        'lowercase',
                                        'my_ascii_folding',
                                        'autocomplete_filter',
                                    ],
                                ],
                            ],
                        ],
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
     * Shortcut to index all pages and all tags.
     *
     * @return self
     *
     * @see ::indexAllTags()
     * @see ::indexAllPages()
     */
    public function indexAll(): self
    {
        return $this
            ->indexAllPages()
            ->indexAllTags();
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
        $params = [
            'index' => $this->getIndexName(),
            'id' => $page->getUid(),
            'body' => array_merge(
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
            ),
        ];

        $this->getClient()->index($params);

        return $this;
    }

    /**
     * Gets all pages of current application and index these.
     *
     * @param bool $memoryHardCleanup
     * @param SymfonyStyle|null $output
     *
     * @return self
     * @see ::indexPage
     */
    public function indexAllPages(bool $memoryHardCleanup = false, ?SymfonyStyle $output = null): self
    {
        foreach ($this->getAllPages() as $page) {
            if ($page->getState() === Page::STATE_DELETED) {
                try {
                    $this->getClient()->delete(
                        [
                            'index' => $this->getIndexName(),
                            'id' => $page->getUid(),
                        ]
                    );
                } catch (Missing404Exception $exception) {
                    // It means that page has already been deleted from Elasticsearch indices, nothing to do
                    $this->logger->warning(sprintf('%s : %s :%s', __CLASS__, __FUNCTION__, $exception->getMessage()));
                }
            } else {
                $this->indexPage($page);
                if ($output) {
                    $output->progressAdvance();
                }
            }


            if ($memoryHardCleanup) {
                $this->entityMgr->clear();
                gc_disable();
                gc_enable();
            }
        }

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
                'body' => array_merge(
                    [
                        'name' => $tag->getKeyWord(),
                        'source' => Tag::SOURCE_TYPE,
                    ],
                    $this->getTagCustomDataToIndex($tag)
                ),
            ]
        );

        return $this;
    }

    /**
     * Indexes all tags into Elasticsearch except the root keyword.
     *
     * @param SymfonyStyle|null $output
     *
     * @return self
     */
    public function indexAllTags(?SymfonyStyle $output = null): self
    {
        $rootUid = md5('root');
        foreach ($this->entityMgr->getRepository(Tag::class)->findAll() as $tag) {
            if ($rootUid !== $tag->getUid()) {
                $this->indexTag($tag);
                if ($output) {
                    $output->progressAdvance();
                }
            }
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
        return self::ELASTICSEARCH_INDEX_NAME . '_' . $this->getAnalyzerName();
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
                    'properties' => [
                        'title' => [
                            'type' => 'text',
                            'analyzer' => $this->getAnalyzerName(),
                            'fields' => [
                                'raw' => [
                                    'type' => 'keyword',
                                ],
                                'folded' => [
                                    'type' => 'text',
                                    'analyzer' => 'std_folded',
                                ],
                            ],
                        ],
                        'first_heading' => [
                            'type' => 'text',
                            'analyzer' => $this->getAnalyzerName(),
                            'fields' => [
                                'raw' => [
                                    'type' => 'keyword',
                                ],
                                'folded' => [
                                    'type' => 'text',
                                    'analyzer' => 'std_folded',
                                ],
                            ],
                        ],
                        'abstract_uid' => [
                            'type' => 'keyword',
                        ],
                        'url' => [
                            'type' => 'keyword',
                        ],
                        'image_uid' => [
                            'type' => 'keyword',
                        ],
                        'contents' => [
                            'type' => 'text',
                            'analyzer' => $this->getAnalyzerName(),
                            'fields' => [
                                'folded' => [
                                    'type' => 'text',
                                    'analyzer' => 'std_folded',
                                ],
                            ],
                        ],
                        'tags' => [
                            'type' => 'text',
                            'analyzer' => $this->getAnalyzerName(),
                            'fields' => [
                                'raw' => [
                                    'type' => 'keyword',
                                ],
                                'folded' => [
                                    'type' => 'text',
                                    'analyzer' => 'std_folded',
                                ],
                            ],
                        ],
                        'has_draft_contents' => [
                            'type' => 'boolean',
                        ],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss',
                        ],
                        'modified_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss',
                        ],
                        'published_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss',
                        ],
                        'type' => [
                            'type' => 'keyword',
                        ],
                        'is_online' => [
                            'type' => 'boolean',
                        ],
                        'is_pullable' => [
                            'type' => 'boolean',
                        ],
                        'category' => [
                            'type' => 'keyword',
                        ],
                        'source' => [
                            'type' => 'keyword',
                        ],
                        'lang' => [
                            'type' => 'keyword',
                        ],
                        'level' => [
                            'type' => 'integer',
                        ],
                        'state' => [
                            'type' => 'integer',
                        ],
                        'seo_index' => [
                            'type' => 'boolean',
                        ],
                        'seo_follow' => [
                            'type' => 'boolean',
                        ]
                    ],
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
                        $this->getCustomTagTypeProperties(),
                        [
                            'name' => [
                                'type' => 'text',
                                'analyzer' => 'autocomplete',
                                'search_analyzer' => 'standard',
                                'fielddata' => true,
                            ],
                            'source' => [
                                'type' => 'keyword',
                            ],
                            'parents' => [
                                'type' => 'keyword',
                            ],
                        ]
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
     * Get all pages.
     *
     * @return Generator|null
     */
    protected function getAllPages(): ?Generator
    {
        try {
            $stmt = $this->entityMgr->getConnection()->query('SELECT uid FROM page');
            while ($row = $stmt->fetch()) {
                yield $this->entityMgr->find(Page::class, $row['uid']);
            }
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Get total of undeleted pages.
     *
     * @return int
     */
    public function getTotalOfUndeletedPages(): int
    {
        $total = 0;

        try {
            $total = $this
                ->entityMgr
                ->getRepository(Page::class)
                ->createQueryBuilder('p')
                ->select('count(p._uid)')
                ->where('p._state != :state')
                ->setParameter('state', Page::STATE_DELETED)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $total;
    }

    /**
     * Get total of tags.
     *
     * @return int
     */
    public function getTotalOfTags(): int
    {
        $total = 0;

        try {
            $total = $this
                ->entityMgr
                ->getRepository(Tag::class)
                ->createQueryBuilder('k')
                ->select('count(k._uid)')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $total;
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
     * Get analyser name.
     *
     * @return string
     */
    protected function getAnalyzerName(): string
    {
        return $this->getAnalyzerRegistry() === null ?
            self::DEFAULT_ANALYZER : $this->getAnalyzerRegistry()->getValue();
    }
}
