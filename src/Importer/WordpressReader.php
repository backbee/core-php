<?php

namespace BackBeePlanet\Importer;

use GuzzleHttp\Client;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class WordpressReader implements ReaderInterface
{
    const TYPE_UNIQUE_NAME = 'wordpress';

    /**
     * @var Client
     */
    private $http;

    public function __construct()
    {
        $this->http = new Client(
            [
                'timeout' => 20,
            ]
        );
    }

    /**
     * {@inehritdoc}
     */
    public function name()
    {
        return self::TYPE_UNIQUE_NAME;
    }

    /**
     * {@inehritdoc}
     *
     * @param string $source must be the domain of the Wordpress API.
     *
     * @return bool
     */
    public function verify($source)
    {
        return false !== $this->sourceMetadata($source);
    }

    /**
     * {@inehritdoc}
     *
     * @param string $source {@see ::verify()}
     *
     * @return \Generator
     */
    public function collect($source)
    {
        if (!$this->verify($source)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot connect to Wordpress API with provided source "%s".',
                    $source
                )
            );
        }

        $baseUrl = str_replace([':/', '//'], ['://', '/'], $source . '/wp-json/wp/v2');

        $maxPages = (int)$this->sourceMetadata($source)['max_pages'];
        for ($i = 1; $i < $maxPages + 1; $i++) {
            foreach ((array)$this->getRequestResult(
                $baseUrl . '/posts?orderby=date&order=desc&status=publish&page=' . $i
            ) as $row) {
                $row['tags'] = $row['tags'] ?? [];
                $row['tags'] = (array)$this->getRequestResult($baseUrl . '/tags?post=' . $row['id']);

                $row['img'] = '';
                if (false !== $row['featured_media']) {
                    try {
                        $row['img'] = $this->getRequestResult($baseUrl . '/media/' . $row['featured_media']);
                    } catch (\Exception $e) {
                        // nothing to do...
                    }
                }

                $url = parse_url($row['link'])['path'];
                $row['url'] = [$url];
                if (1 === preg_match('~/$~', $url)) {
                    $row['url'][] = substr($url, 0, -1);
                }

                yield $this->format($row);
            }
        }
    }

    public function sourceMetadata($source)
    {
        $metadata = [];
        try {
            $response = $this->http->head(
                str_replace(
                    [':/', '//'],
                    ['://', '/'],
                    $source . '/wp-json/wp/v2/posts'
                )
            );

            $metadata['max_items'] = $response->getHeaderLine('X-WP-Total');
            $metadata['max_pages'] = $response->getHeaderLine('X-WP-TotalPages');
        } catch (\Exception $e) {
            // nothing to do...
        }

        return $metadata;
    }

    /**
     * {@inehritdoc}
     */
    public function supports($type): bool
    {
        return self::TYPE_UNIQUE_NAME === $type;
    }

    protected function getRequestResult($url)
    {
        $response = $this->http->get($url);
        if (
            200 !== $response->getStatusCode()
            || false === strpos($response->getHeaderLine('Content-Type'), 'application/json')
        ) {
            return null;
        }

        $raw = (string)$response->getBody();

        return json_decode($raw, true);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function format(array $data): array
    {
        $result = [
            'page' => [
                'uid' => md5($data['guid']['rendered']),
                'title' => html_entity_decode($data['title']['rendered']),
                'type' => 'article',
                'menu' => 'none',
                'tags' => array_map(
                    static function ($item) {
                        return $item['name'];
                    },
                    $data['tags']
                ),
                'published_at' => $data['date'],
                'created_at' => $data['date'],
                'modified_at' => $data['modified'],
            ],
        ];

        $url = array_pop($data['url']);
        $result['page']['redirections'] = [
            $url,
            str_replace('//', '/', $url . '/'),
        ];

        $text = str_replace(["\n", "\r"], '', $data['content']['rendered']);
        $text = preg_replace('/<!--(.|\s)*?-->/', '', $text);
        $pos = strpos($text, '</p>');
        $rawAbstract = substr($text, 0, $pos) . '</p>';
        $abstract = strip_tags($rawAbstract);
        $text = str_replace(strip_tags($rawAbstract, '<p><i><b><strong><em><span>'), '', $text);

        $result['contents'] = [
            'row_1' => [
                'columns' => [
                    'col_1' => [
                        [
                            'type' => 'Article/ArticleTitle',
                            'data' => [
                                'text' => $result['page']['title'],
                            ],
                        ],
                    ],
                ],
            ],
            'row_2' => [
                'columns' => [
                    'col_1' => [
                        [
                            'type' => 'Media/Image',
                            'data' => [
                                'path' => $data['img']['guid']['rendered'] ?? ''
                                ,
                                'parameters' => [
                                    'auto_height' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'row_3' => [
                'data' => [
                    'parameters' => [
                        'width' => 'container container-small',
                    ],
                ],
                'columns' => [
                    'col_1' => [
                        [
                            'type' => 'Article/ArticleAbstract',
                            'data' => [
                                'text' => $abstract,
                            ],
                        ],
                    ],
                ],
            ],
            'row_4' => [
                'data' => [
                    'parameters' => [
                        'width' => 'container container-small',
                    ],
                ],
                'columns' => [
                    'col_1' => [
                        [
                            'type' => 'Text/Paragraph',
                            'data' => [
                                'text' => $text,
                            ],
                        ],
                    ],
                ],
            ],
            'row_5' => [
                'data' => [
                    'parameters' => [
                        'width' => 'container container-small',
                    ],
                ],
                'columns' => [
                    'col_1' => [
                        [
                            'is_unique' => true,
                            'type' => 'Comment/Disqus',
                            'data' => [],
                        ],
                    ],
                ],
            ],
        ];

        if (false === $data['img']) {
            unset($result['contents']['row_2']);
        }

        return $result;
    }
}
